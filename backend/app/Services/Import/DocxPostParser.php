<?php

namespace App\Services\Import;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\Image;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocxPostParser
{
    public function parse(string $filePath, int $batchId): array
    {
        try {
            $phpWord = IOFactory::load($filePath);
            $posts = [];
            $currentPost = null;
            $postIndex = 0;

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $this->processElement($element, $currentPost, $posts, $postIndex, $batchId);
                }
            }

            // Save the last post
            if ($currentPost !== null) {
                $this->finalizePost($currentPost);
                $posts[] = $currentPost;
            }

            return $posts;

        } catch (\Exception $e) {
            Log::error('Docx parsing failed: ' . $e->getMessage());
            throw new \Exception('Không thể đọc file Word. Vui lòng kiểm tra lại định dạng.');
        }
    }

    protected function processElement($element, &$currentPost, &$posts, &$postIndex, $batchId)
    {
        $text = '';
        $isImage = false;
        $imageData = null;
        $imageExt = null;

        if (method_exists($element, 'getElements')) {
            // It's a container like TextRun
            foreach ($element->getElements() as $childElement) {
                $this->processElement($childElement, $currentPost, $posts, $postIndex, $batchId);
            }
            return;
        }

        if ($element instanceof Text) {
            $text = $element->getText();
        } elseif ($element instanceof Image) {
            $isImage = true;
            $imageData = $element->getImageStringData(true);
            $imageExt = $element->getImageExtension();
        } elseif (method_exists($element, 'getText')) {
            $text = $element->getText();
        }

        $text = trim((string)$text);

        // Check if this is the start of a new post
        // Matches "Bài 01", "Bài 1", "BÀI 01", "Bài 01 | Tên Bài", "Bài 01(Done)"
        if (!empty($text) && preg_match('/^BÀI\s*\d+/i', $text)) {
            // Save previous post
            if ($currentPost !== null) {
                $this->finalizePost($currentPost);
                $posts[] = $currentPost;
            }

            $postIndex++;
            $currentPost = $this->getEmptyPostTemplate($postIndex);
            
            // Extract title if attached to the "Bài XX" line
            // Example: "Bài 01 | Cách trị đau lưng" or "Bài 01: Cách trị đau lưng"
            if (preg_match('/^BÀI\s*\d+[\s\|\:\-]+(.*)/i', $text, $matches)) {
                $currentPost['title'] = trim($matches[1]);
            } else {
                $currentPost['title'] = $text; // Fallback
            }
            return;
        }

        if ($currentPost === null) {
            // Ignore text before the first "Bài XX"
            return;
        }

        // Process Image
        if ($isImage && $imageData) {
            // Save image to temp storage
            $filename = 'batch_' . $batchId . '_post_' . $postIndex . '_' . Str::random(8) . '.' . $imageExt;
            $path = 'temp/imports/' . $filename;
            
            // Decode base64
            $decodedData = base64_decode($imageData);
            if ($decodedData) {
                Storage::disk('public')->put($path, $decodedData);
                $currentPost['images'][] = $path; // Store relative path
            }
            return;
        }

        if (empty($text)) {
            return;
        }

        // Process Hashtags
        if (str_starts_with($text, '#')) {
            $tags = preg_split('/[\s,]+/', $text);
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $currentPost['hashtags'][] = $tag;
                }
            }
            return;
        }

        // Process Date/Time
        if (preg_match('/^(Ngày đăng|Publish date)\s*[\:\-]\s*(.+)/i', $text, $matches)) {
            $currentPost['publish_date'] = trim($matches[2]);
            return;
        }
        if (preg_match('/^(Giờ đăng|Publish time)\s*[\:\-]\s*(.+)/i', $text, $matches)) {
            $currentPost['publish_time'] = trim($matches[2]);
            return;
        }

        // Process CTA (heuristics)
        if (str_contains($text, '💬') || preg_match('/^(Liên hệ|Inbox|Comment|Gọi ngay|Hotline)/i', $text)) {
            $currentPost['cta'] .= $text . "\n";
            return;
        }

        // Skip marker lines like "Hình ảnh Bài XX"
        if (preg_match('/^Hình ảnh BÀI\s*\d+/i', $text)) {
            return;
        }

        // Otherwise, it's content
        // If title is just "Bài 01" and we haven't set a real title, the first line of content is often the title.
        if (preg_match('/^BÀI\s*\d+$/i', $currentPost['title']) && empty($currentPost['content'])) {
            $currentPost['title'] = $text;
            return;
        }

        $currentPost['content'] .= $text . "\n";
    }

    protected function finalizePost(&$post)
    {
        $post['content'] = trim($post['content']);
        $post['cta'] = trim($post['cta']);
        $post['hashtags'] = array_values(array_unique($post['hashtags']));
    }

    protected function getEmptyPostTemplate($index)
    {
        return [
            '_row_number' => $index,
            'title' => '',
            'content' => '',
            'cta' => '',
            'hashtags' => [],
            'images' => [],
            'publish_date' => null,
            'publish_time' => null,
        ];
    }
}
