<?php

namespace App\Providers\AiProvider;

use App\Contracts\ContentAiProviderInterface;
use App\DTOs\ContentGenerationData;
use App\DTOs\ContentGenerationResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Exceptions\HttpResponseException;

class OllamaContentProvider implements ContentAiProviderInterface
{
    public function generate(ContentGenerationData $data): ContentGenerationResult
    {
        return $this->doGenerate($data, false);
    }

    private function doGenerate(ContentGenerationData $data, bool $isRetry, string $previousInvalidJson = null): ContentGenerationResult
    {
        $prompt = $this->buildPrompt($data);
        
        if ($isRetry) {
            $prompt .= "\n\nCẢNH BÁO: Trong lần phản hồi trước, bạn đã trả về kết quả không hợp lệ sau:\n" . $previousInvalidJson . "\n\nHãy sửa lại thành JSON hợp lệ theo đúng schema được yêu cầu.";
        }

        $baseUrl = config('services.ollama.base_url');
        $model = config('services.ollama.model');
        $timeout = config('services.ollama.timeout');

        try {
            $response = Http::timeout($timeout)->post("{$baseUrl}/api/generate", [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json'
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            if (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')) {
                $this->throwError('OLLAMA_TIMEOUT', 'AI phản hồi quá thời gian cho phép. Hãy thử lại với nội dung ngắn hơn.', 504);
            }
            $this->throwError('OLLAMA_CONNECTION_FAILED', 'Không thể kết nối với Ollama. Hãy kiểm tra dịch vụ Ollama và thử lại.', 502);
        } catch (\Exception $e) {
            $this->throwError('CONTENT_GENERATION_FAILED', 'Có lỗi xảy ra khi kết nối AI.', 500);
        }

        if (!$response->successful()) {
            if ($response->status() == 404) {
                $this->throwError('OLLAMA_MODEL_NOT_FOUND', 'Không tìm thấy model AI được cấu hình. Hãy kiểm tra model Ollama.', 404);
            }
            $this->throwError('CONTENT_GENERATION_FAILED', 'Lỗi không xác định từ dịch vụ AI.', 500);
        }

        $result = $response->json();
        if (empty($result['response'])) {
            $this->throwError('INVALID_AI_RESPONSE', 'Phản hồi từ AI không chứa dữ liệu.', 502);
        }

        $rawJson = $this->cleanMarkdownJson($result['response']);
        $parsed = json_decode($rawJson, true);

        if (!$this->isValidSchema($parsed, $data->numberOfVersions)) {
            if (!$isRetry) {
                Log::warning('Ollama returned invalid schema, retrying...', ['raw' => $rawJson]);
                return $this->doGenerate($data, true, $rawJson);
            }
            $this->throwError('INVALID_AI_RESPONSE', 'AI trả về kết quả chưa đúng định dạng. Hãy thử tạo lại nội dung.', 502);
        }

        return new ContentGenerationResult(
            versions: $parsed['versions'],
            metadata: [
                'model' => $model,
                'provider' => 'ollama',
                'generated_at' => Carbon::now()->toIso8601String()
            ]
        );
    }

    private function cleanMarkdownJson(string $raw): string
    {
        $raw = trim($raw);
        if (str_starts_with($raw, '```json')) {
            $raw = substr($raw, 7);
        } elseif (str_starts_with($raw, '```')) {
            $raw = substr($raw, 3);
        }
        if (str_ends_with($raw, '```')) {
            $raw = substr($raw, 0, -3);
        }
        return trim($raw);
    }

    private function isValidSchema($parsed, $expectedVersions): bool
    {
        if (!$parsed || !is_array($parsed) || !isset($parsed['versions']) || !is_array($parsed['versions'])) {
            return false;
        }

        if (count($parsed['versions']) !== $expectedVersions) {
            return false;
        }

        foreach ($parsed['versions'] as $version) {
            if (empty($version['title']) || empty($version['content']) || empty($version['cta']) || !isset($version['hashtags']) || !is_array($version['hashtags'])) {
                return false;
            }
        }

        return true;
    }

    private function buildPrompt(ContentGenerationData $data): string
    {
        $objectiveMap = [
            'sales' => 'Bán hàng',
            'introduction' => 'Giới thiệu sản phẩm',
            'promotion' => 'Chương trình ưu đãi',
            'engagement' => 'Tăng tương tác',
            'education' => 'Chia sẻ kiến thức',
            'event' => 'Quảng bá sự kiện'
        ];
        $toneMap = [
            'professional' => 'Chuyên nghiệp',
            'friendly' => 'Thân thiện',
            'youthful' => 'Trẻ trung',
            'humorous' => 'Hài hước',
            'luxurious' => 'Sang trọng',
            'inspirational' => 'Truyền cảm hứng'
        ];
        $lengthMap = [
            'short' => 'Khoảng 80-120 từ',
            'medium' => 'Khoảng 150-250 từ',
            'long' => 'Khoảng 300-450 từ'
        ];

        $prompt = "Bạn là một chuyên gia sáng tạo nội dung mạng xã hội chuyên nghiệp. Nhiệm vụ của bạn là viết bài đăng Facebook bằng Tiếng Việt dựa trên thông tin sau.\n\n";

        if ($data->brand) {
            $prompt .= "--- 1. BRAND PROFILE (THÔNG TIN THƯƠNG HIỆU) ---\n";
            $prompt .= "- Tên thương hiệu: " . $data->brand->name . "\n";
            if ($data->brand->industry) $prompt .= "- Ngành hàng: " . $data->brand->industry . "\n";
            if ($data->brand->description) $prompt .= "- Mô tả: " . $data->brand->description . "\n";
            if ($data->brand->products_services) $prompt .= "- Sản phẩm/dịch vụ cốt lõi: " . $data->brand->products_services . "\n";
            if ($data->brand->slogan) $prompt .= "- Slogan: " . $data->brand->slogan . "\n";
            if (!empty($data->brand->writing_rules)) {
                $prompt .= "- QUY TẮC VIẾT BÀI CỦA THƯƠNG HIỆU:\n  + " . implode("\n  + ", $data->brand->writing_rules) . "\n";
            }
            if ($data->brand->default_cta) $prompt .= "- Lời kêu gọi hành động mặc định: " . $data->brand->default_cta . "\n";
            $prompt .= "------------------------------------------------\n\n";
        }

        if ($data->template) {
            $prompt .= "--- 2. CONTENT TEMPLATE (MẪU NỘI DUNG) ---\n";
            $prompt .= "- Mục tiêu: " . $objectiveMap[$data->template->objective] . "\n";
            if ($data->template->opening_style) $prompt .= "- Phong cách mở bài: " . $data->template->opening_style . "\n";
            if (!empty($data->template->body_structure)) {
                $prompt .= "- Cấu trúc thân bài:\n  + " . implode("\n  + ", $data->template->body_structure) . "\n";
            }
            if ($data->template->cta_instruction) $prompt .= "- Hướng dẫn Call To Action: " . $data->template->cta_instruction . "\n";
            if ($data->template->hashtag_instruction) $prompt .= "- Hướng dẫn Hashtag: " . $data->template->hashtag_instruction . "\n";
            if ($data->template->additional_instruction) $prompt .= "- Chỉ dẫn bổ sung: " . $data->template->additional_instruction . "\n";
            $prompt .= "------------------------------------------\n\n";
        }

        $prompt .= "--- 3. YÊU CẦU TRỰC TIẾP TỪ NGƯỜI DÙNG ---\n";
        $prompt .= "- Chủ đề/Sản phẩm: " . $data->topic . "\n";
        $prompt .= "- Thông tin chính: " . $data->mainInformation . "\n";
        if (!empty($data->targetAudience)) {
            $prompt .= "- Khách hàng mục tiêu: " . $data->targetAudience . "\n";
        }
        $prompt .= "- Giọng văn: " . $toneMap[$data->tone] . "\n";
        $prompt .= "- Độ dài yêu cầu: " . $lengthMap[$data->length] . "\n";
        
        if (!empty($data->requiredKeywords)) {
            $prompt .= "- Từ khóa bắt buộc (phải sử dụng tự nhiên): " . implode(", ", $data->requiredKeywords) . "\n";
        }
        if (!empty($data->excludedContent)) {
            $prompt .= "- NỘI DUNG CẦN TRÁNH TUYỆT ĐỐI:\n  + " . implode("\n  + ", $data->excludedContent) . "\n";
        }
        $prompt .= "------------------------------------------\n\n";

        $prompt .= "--- 4. SYSTEM RULES (YÊU CẦU BẮT BUỘC) ---\n";
        $prompt .= "1. CHỈ VIẾT BẰNG TIẾNG VIỆT.\n";
        $prompt .= "2. Phù hợp để đăng lên Facebook Page (sử dụng format phù hợp, giãn dòng tốt).\n";
        $prompt .= "3. KHÔNG tự tạo giá bán, số liệu, thành phần, công dụng hoặc khuyến mãi mà người dùng chưa cung cấp. KHÔNG cam kết hiệu quả không có căn cứ. KHÔNG so sánh với đối thủ nếu không được yêu cầu.\n";
        $prompt .= "4. Mỗi hashtag PHẢI viết liền không có khoảng trắng.\n";
        $prompt .= "5. KHÔNG lạm dụng emoji (tối đa 3-5 emoji mỗi bài).\n";
        $prompt .= "6. Yêu cầu tạo đúng " . $data->numberOfVersions . " phiên bản hoàn toàn khác nhau về tiêu đề, cách mở đầu và hướng triển khai.\n";
        $prompt .= "7. TRẢ VỀ DUY NHẤT ĐỊNH DẠNG JSON. KHÔNG SỬ DỤNG MARKDOWN CODE FENCE (```json). KHÔNG CÓ BẤT KỲ VĂN BẢN NÀO TRƯỚC HAY SAU JSON.\n";
        $prompt .= "8. JSON phải có cấu trúc chính xác như sau:\n";
        $prompt .= '{"versions":[{"title":"Tiêu đề bài viết","content":"Nội dung chính","cta":"Lời kêu gọi hành động","hashtags":["#Hashtag1","#Hashtag2"]}]}';

        return $prompt;
    }

    private function throwError($code, $message, $status)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => $code
        ], $status));
    }
}
