<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostVersion;
use App\Models\PostActivityLog;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class PostWorkflowService
{
    /**
     * Calculate content hash for version tracking
     */
    public function calculateHash(string $title, string $content, ?string $cta, ?array $hashtags): string
    {
        $hashStr = $title . '|' . $content . '|' . $cta . '|' . json_encode($hashtags);
        return md5($hashStr);
    }

    /**
     * Log an activity for a post
     */
    public function logActivity(Post $post, string $action, ?string $fromStatus = null, ?string $toStatus = null, array $metadata = [], ?int $userId = null)
    {
        PostActivityLog::create([
            'post_id' => $post->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => $metadata,
            'performed_by' => $userId,
        ]);
    }

    /**
     * Create a new version of the post
     */
    public function createVersion(Post $post, string $changeSource, ?string $changeSummary = null, ?int $userId = null): PostVersion
    {
        $hash = $this->calculateHash($post->title, $post->content, $post->cta, $post->hashtags);
        
        $post->content_version = $post->content_version + 1;
        $post->last_content_hash = $hash;
        $post->saveQuietly(); // Avoid triggering updated_at if not needed, though it's fine

        return PostVersion::create([
            'post_id' => $post->id,
            'version_number' => $post->content_version,
            'title' => $post->title,
            'content' => $post->content,
            'cta' => $post->cta,
            'hashtags' => $post->hashtags,
            'content_hash' => $hash,
            'quality_score' => $post->quality_score,
            'quality_status' => $post->quality_status,
            'quality_result' => $post->quality_result,
            'change_source' => $changeSource,
            'change_summary' => $changeSummary,
            'created_by' => $userId,
        ]);
    }

    /**
     * Handle manual edit (clears quality score and increments version if changed)
     */
    public function handleManualEdit(Post $post, array $newData, ?int $userId = null)
    {
        $oldHash = $post->last_content_hash;
        
        // Temporarily set new data to calculate hash
        $tempTitle = $newData['title'] ?? $post->title;
        $tempContent = $newData['content'] ?? $post->content;
        $tempCta = $newData['cta'] ?? $post->cta;
        $tempHashtags = $newData['hashtags'] ?? $post->hashtags;
        
        $newHash = $this->calculateHash($tempTitle, $tempContent, $tempCta, $tempHashtags);

        $contentChanged = $oldHash !== $newHash;

        $post->fill($newData);
        $post->last_saved_at = Carbon::now();
        $post->last_edited_by = $userId;

        if ($contentChanged) {
            $post->quality_score = null;
            $post->quality_status = 'unchecked';
            $post->quality_result = null;
            $post->quality_checked_at = null;
            
            $post->save();
            $this->createVersion($post, 'manual_edit', 'User edited content', $userId);
            $this->logActivity($post, 'updated', $post->status, $post->status, ['fields_changed' => true], $userId);
        } else {
            $post->save();
        }

        return $post;
    }

    /**
     * Submit for review
     */
    public function submitForReview(Post $post, ?int $userId = null)
    {
        if (!in_array($post->status, ['draft', 'changes_requested'])) {
            throw new Exception("Chỉ có thể gửi duyệt từ bản nháp hoặc khi được yêu cầu chỉnh sửa.", 422);
        }

        if (empty($post->title) || empty($post->content)) {
            throw new Exception("Bài viết chưa có nội dung để gửi duyệt.", 422);
        }

        if ($post->quality_status === 'failed' || $post->quality_status === 'unchecked' || $post->quality_status === null) {
            throw new Exception("Bài viết phải vượt qua kiểm tra chất lượng trước khi gửi duyệt.", 422);
        }

        DB::beginTransaction();
        try {
            $oldStatus = $post->status;
            
            // Create a snapshot version
            $this->createVersion($post, 'status_change', 'Submitted for review', $userId);
            
            $post->status = 'in_review';
            $post->submitted_for_review_at = Carbon::now();
            $post->save();

            $this->logActivity($post, 'submitted_for_review', $oldStatus, 'in_review', [], $userId);
            
            DB::commit();
            return $post;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Approve post
     */
    public function approve(Post $post, ?int $userId = null)
    {
        if ($post->status !== 'in_review') {
            throw new Exception("Chỉ có thể duyệt bài đang chờ duyệt.", 422);
        }

        DB::beginTransaction();
        try {
            $post->status = 'approved';
            $post->approved_at = Carbon::now();
            $post->approved_by = $userId;
            $post->save();

            $this->logActivity($post, 'approved', 'in_review', 'approved', [], $userId);
            
            DB::commit();
            return $post;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Request changes
     */
    public function requestChanges(Post $post, string $note, ?int $userId = null)
    {
        if (!in_array($post->status, ['in_review', 'approved'])) {
            throw new Exception("Không thể yêu cầu chỉnh sửa ở trạng thái này.", 422);
        }

        if (empty(trim($note))) {
            throw new Exception("Phải nhập lý do yêu cầu chỉnh sửa.", 422);
        }

        DB::beginTransaction();
        try {
            $oldStatus = $post->status;
            $post->status = 'changes_requested';
            $post->review_note = trim($note);
            $post->save();

            $this->logActivity($post, 'changes_requested', $oldStatus, 'changes_requested', ['note' => $post->review_note], $userId);
            
            DB::commit();
            return $post;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mark ready for publication
     */
    public function markReady(Post $post, ?int $userId = null)
    {
        if ($post->status !== 'approved') {
            throw new Exception("Chỉ có thể đánh dấu sẵn sàng cho bài đã được duyệt.", 422);
        }

        $currentHash = $this->calculateHash($post->title, $post->content, $post->cta, $post->hashtags);
        if ($currentHash !== $post->last_content_hash) {
            throw new Exception("Nội dung đã bị thay đổi sau khi duyệt, vui lòng gửi duyệt lại.", 422);
        }

        DB::beginTransaction();
        try {
            $post->status = 'ready';
            $post->ready_at = Carbon::now();
            $post->save();

            $this->logActivity($post, 'marked_ready', 'approved', 'ready', [], $userId);
            
            DB::commit();
            return $post;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Return to draft
     */
    public function returnToDraft(Post $post, ?int $userId = null)
    {
        if (in_array($post->status, ['draft', 'changes_requested'])) {
            throw new Exception("Bài viết đã ở trạng thái có thể chỉnh sửa.", 422);
        }

        DB::beginTransaction();
        try {
            $oldStatus = $post->status;
            $post->status = 'draft';
            $post->save();

            $this->logActivity($post, 'returned_to_draft', $oldStatus, 'draft', [], $userId);
            
            DB::commit();
            return $post;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Restore to a previous version
     */
    public function restoreVersion(Post $post, PostVersion $version, ?int $userId = null)
    {
        if (in_array($post->status, ['in_review', 'approved', 'ready'])) {
            throw new Exception("Phải đưa bài viết về bản nháp trước khi khôi phục.", 422);
        }

        DB::beginTransaction();
        try {
            $post->title = $version->title;
            $post->content = $version->content;
            $post->cta = $version->cta;
            $post->hashtags = $version->hashtags;
            
            // Note: quality status is carried over or cleared? We carry it over from version
            $post->quality_score = $version->quality_score;
            $post->quality_status = $version->quality_status;
            $post->quality_result = $version->quality_result;
            
            $post->save();
            
            $this->createVersion($post, 'restored', "Restored from version {$version->version_number}", $userId);
            $this->logActivity($post, 'restored_version', $post->status, $post->status, ['restored_version' => $version->version_number], $userId);
            
            DB::commit();
            return $post;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
