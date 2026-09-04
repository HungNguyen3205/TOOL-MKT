<?php

namespace App\Services;

use App\DTOs\ContentGenerationData;

class ContentQualityValidator
{
    protected int $passScore;
    protected int $warningScore;
    protected float $similarityThreshold;
    protected int $maxEmoji;

    public function __construct()
    {
        $this->passScore = config('services.ai_quality.pass_score', 80);
        $this->warningScore = config('services.ai_quality.warning_score', 60);
        $this->similarityThreshold = config('services.ai_quality.similarity_threshold', 0.85);
        $this->maxEmoji = config('services.ai_quality.max_emoji', 5);
    }

    public function validateVersions(array $versions, ContentGenerationData $data): array
    {
        $validatedVersions = [];
        $suspiciousRegex = '/(\d+%|giảm giá|sale|cam kết|chắc chắn|100%|tốt nhất|rẻ nhất|số 1|\d{4,}\s*(vnđ|đ|vnd)|\d+\.\d{3})/iu';

        foreach ($versions as $index => $version) {
            $score = 100;
            $errors = [];
            $warnings = [];
            $missingKeywords = [];
            $prohibitedTermsFound = [];
            $suspiciousClaims = [];
            $similarityWarning = false;

            // 1. Structure Check
            if (empty($version['title']) || empty($version['content'])) {
                $score -= 30;
                $errors[] = 'Thiếu tiêu đề hoặc nội dung.';
            }

            // 2. Required Keywords
            $fullText = mb_strtolower(($version['title'] ?? '') . ' ' . ($version['content'] ?? '') . ' ' . ($version['cta'] ?? ''));
            foreach ($data->requiredKeywords as $kw) {
                if (!str_contains($fullText, mb_strtolower($kw))) {
                    $missingKeywords[] = $kw;
                    $score -= 10;
                }
            }
            if (count($missingKeywords) > 0) {
                $errors[] = 'Thiếu từ khóa bắt buộc.';
            }

            // 3. Prohibited Terms
            foreach ($data->excludedContent as $term) {
                if (str_contains($fullText, mb_strtolower($term))) {
                    $prohibitedTermsFound[] = $term;
                    $score -= 20;
                }
            }
            if (count($prohibitedTermsFound) > 0) {
                $errors[] = 'Chứa nội dung bị cấm.';
            }

            // 4. Hashtag
            $cleanHashtags = [];
            if (!empty($version['hashtags']) && is_array($version['hashtags'])) {
                foreach ($version['hashtags'] as $tag) {
                    $cleanTag = trim($tag);
                    if ($cleanTag) {
                        if (!str_starts_with($cleanTag, '#')) {
                            $cleanTag = '#' . $cleanTag;
                        }
                        $cleanTag = str_replace(' ', '', $cleanTag); // No spaces
                        $cleanHashtags[] = $cleanTag;
                    }
                }
            }
            $cleanHashtags = array_values(array_unique($cleanHashtags));
            $version['hashtags'] = $cleanHashtags;

            // 5. Emoji Count
            $emojiCount = preg_match_all('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', $fullText);
            if ($emojiCount > $this->maxEmoji) {
                $warnings[] = "Quá nhiều emoji ({$emojiCount} > {$this->maxEmoji}).";
                $score -= 5;
            }

            // 6. Suspicious Claims
            if (preg_match_all($suspiciousRegex, $fullText, $matches)) {
                $suspiciousClaims = array_unique($matches[0]);
                $warnings[] = 'Có chứa các cam kết/số liệu có thể do AI tự tạo.';
                $score -= 10;
            }

            // 7. Length Check
            $wordCount = str_word_count(strip_tags($fullText));
            if ($data->length === 'short' && $wordCount > 150) {
                $score -= 5;
                $warnings[] = 'Bài viết quá dài so với yêu cầu.';
            } elseif ($data->length === 'long' && $wordCount < 200) {
                $score -= 5;
                $warnings[] = 'Bài viết quá ngắn so với yêu cầu.';
            }

            $score = max(0, $score);

            $status = 'passed';
            if ($score < $this->passScore) $status = 'warning';
            if ($score < $this->warningScore || count($prohibitedTermsFound) > 0 || (empty($version['title']) && empty($version['content']))) {
                $status = 'failed';
            }

            $version['quality'] = [
                'score' => $score,
                'status' => $status,
                'errors' => $errors,
                'warnings' => $warnings,
                'missing_keywords' => $missingKeywords,
                'prohibited_terms_found' => $prohibitedTermsFound,
                'suspicious_claims' => $suspiciousClaims,
                'similarity_warning' => false,
                'emoji_count' => $emojiCount
            ];

            $validatedVersions[] = $version;
        }

        // Similarity check across versions
        $validatedVersions = $this->checkSimilarity($validatedVersions);

        return $validatedVersions;
    }

    private function checkSimilarity(array $versions): array
    {
        $count = count($versions);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $text1 = mb_strtolower($versions[$i]['title'] . $versions[$i]['content']);
                $text2 = mb_strtolower($versions[$j]['title'] . $versions[$j]['content']);
                
                $sim = 0;
                similar_text($text1, $text2, $sim);
                
                if (($sim / 100) >= $this->similarityThreshold) {
                    // Cả 2 đều bị trừ điểm
                    if (!$versions[$i]['quality']['similarity_warning']) {
                        $versions[$i]['quality']['similarity_warning'] = true;
                        $versions[$i]['quality']['score'] = max(0, $versions[$i]['quality']['score'] - 15);
                        $versions[$i]['quality']['warnings'][] = 'Nội dung quá giống với phiên bản khác.';
                    }
                    if (!$versions[$j]['quality']['similarity_warning']) {
                        $versions[$j]['quality']['similarity_warning'] = true;
                        $versions[$j]['quality']['score'] = max(0, $versions[$j]['quality']['score'] - 15);
                        $versions[$j]['quality']['warnings'][] = 'Nội dung quá giống với phiên bản khác.';
                    }
                }
            }
        }
        
        // Re-evaluate status after similarity penalty
        foreach ($versions as &$v) {
            $score = $v['quality']['score'];
            $status = $v['quality']['status'];
            if ($status !== 'failed') { // If it's already failed, keep it failed
                if ($score < $this->warningScore) {
                    $v['quality']['status'] = 'failed';
                } elseif ($score < $this->passScore) {
                    $v['quality']['status'] = 'warning';
                }
            }
        }

        return $versions;
    }
}
