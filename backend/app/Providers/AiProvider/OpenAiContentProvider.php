<?php

namespace App\Providers\AiProvider;

use App\Contracts\ContentAiProviderInterface;
use App\DTOs\ContentGenerationData;
use App\DTOs\ContentGenerationResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Exceptions\HttpResponseException;

class OpenAiContentProvider implements ContentAiProviderInterface
{
    public function generate(ContentGenerationData $data): ContentGenerationResult
    {
        $apiKey = config('services.openai.api_key');
        if (empty($apiKey)) {
            $this->throwError('VALIDATION_FAILED', 'Thiếu API Key OpenAI trong cấu hình.', 500);
        }

        $model = config('services.openai.model');
        $timeout = config('services.openai.timeout');
        $maxTokens = config('services.openai.max_output_tokens');

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->buildSystemInstruction($data)],
                        ['role' => 'user', 'content' => $this->buildUserMessage($data)],
                    ],
                    'max_tokens' => (int) $maxTokens,
                    'temperature' => 0.7,
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'facebook_content_versions',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'versions' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'title' => ['type' => 'string'],
                                                'content' => ['type' => 'string'],
                                                'cta' => ['type' => 'string'],
                                                'hashtags' => [
                                                    'type' => 'array',
                                                    'items' => ['type' => 'string']
                                                ]
                                            ],
                                            'required' => ['title', 'content', 'cta', 'hashtags'],
                                            'additionalProperties' => false
                                        ]
                                    ]
                                ],
                                'required' => ['versions'],
                                'additionalProperties' => false
                            ]
                        ]
                    ]
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            if (str_contains($e->getMessage(), 'timed out')) {
                $this->throwError('OLLAMA_TIMEOUT', 'OpenAI phản hồi quá thời gian cho phép.', 504);
            }
            $this->throwError('OLLAMA_CONNECTION_FAILED', 'Không thể kết nối với OpenAI.', 502);
        } catch (\Exception $e) {
            $this->throwError('CONTENT_GENERATION_FAILED', 'Có lỗi xảy ra khi kết nối OpenAI.', 500);
        }

        if (!$response->successful()) {
            if ($response->status() == 401) {
                $this->throwError('VALIDATION_FAILED', 'OpenAI API Key không hợp lệ.', 401);
            }
            if ($response->status() == 429) {
                $this->throwError('CONTENT_GENERATION_FAILED', 'Hết Quota hoặc Rate Limit từ OpenAI.', 429);
            }
            $this->throwError('CONTENT_GENERATION_FAILED', 'Lỗi từ dịch vụ OpenAI: ' . $response->body(), 500);
        }

        $result = $response->json();
        
        if (empty($result['choices'][0]['message']['content'])) {
            $this->throwError('INVALID_AI_RESPONSE', 'Phản hồi từ OpenAI bị rỗng.', 502);
        }

        $rawJson = $result['choices'][0]['message']['content'];
        $parsed = json_decode($rawJson, true);

        if (!$parsed || !isset($parsed['versions']) || count($parsed['versions']) !== $data->numberOfVersions) {
            $this->throwError('INVALID_AI_RESPONSE', 'OpenAI trả về JSON sai schema hoặc thiếu phiên bản.', 502);
        }

        $usage = $result['usage'] ?? [];

        return new ContentGenerationResult(
            versions: $parsed['versions'],
            metadata: [
                'model' => $model,
                'provider' => 'openai',
                'generated_at' => Carbon::now()->toIso8601String(),
                'usage' => $usage
            ]
        );
    }

    private function buildSystemInstruction(ContentGenerationData $data): string
    {
        $prompt = "Bạn là một chuyên gia sáng tạo nội dung mạng xã hội chuyên nghiệp. Nhiệm vụ của bạn là viết bài đăng Facebook bằng Tiếng Việt dựa trên thông tin người dùng cung cấp.\n\n";
        
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

        $prompt .= "--- 2. SYSTEM RULES (YÊU CẦU BẮT BUỘC) ---\n";
        $prompt .= "1. CHỈ VIẾT BẰNG TIẾNG VIỆT.\n";
        $prompt .= "2. Phù hợp để đăng lên Facebook Page (sử dụng format phù hợp, giãn dòng tốt).\n";
        $prompt .= "3. KHÔNG tự tạo giá bán, số liệu, thành phần, công dụng hoặc khuyến mãi mà người dùng chưa cung cấp. KHÔNG cam kết hiệu quả không có căn cứ. KHÔNG so sánh với đối thủ nếu không được yêu cầu.\n";
        $prompt .= "4. Mỗi hashtag PHẢI viết liền không có khoảng trắng.\n";
        $prompt .= "5. KHÔNG lạm dụng emoji (tối đa 3-5 emoji mỗi bài).\n";
        $prompt .= "6. Yêu cầu tạo đúng " . $data->numberOfVersions . " phiên bản hoàn toàn khác nhau về tiêu đề, cách mở đầu và hướng triển khai.\n";

        return $prompt;
    }

    private function buildUserMessage(ContentGenerationData $data): string
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

        $msg = "";
        if ($data->template) {
            $msg .= "--- 3. CONTENT TEMPLATE (MẪU NỘI DUNG) ---\n";
            $msg .= "- Mục tiêu: " . $objectiveMap[$data->template->objective] . "\n";
            if ($data->template->opening_style) $msg .= "- Phong cách mở bài: " . $data->template->opening_style . "\n";
            if (!empty($data->template->body_structure)) {
                $msg .= "- Cấu trúc thân bài:\n  + " . implode("\n  + ", $data->template->body_structure) . "\n";
            }
            if ($data->template->cta_instruction) $msg .= "- Hướng dẫn Call To Action: " . $data->template->cta_instruction . "\n";
            if ($data->template->hashtag_instruction) $msg .= "- Hướng dẫn Hashtag: " . $data->template->hashtag_instruction . "\n";
            if ($data->template->additional_instruction) $msg .= "- Chỉ dẫn bổ sung: " . $data->template->additional_instruction . "\n";
            $msg .= "------------------------------------------\n\n";
        }

        $msg .= "--- 4. THÔNG TIN TRỰC TIẾP TỪ NGƯỜI DÙNG ---\n";
        $msg .= "Chủ đề/Sản phẩm: " . $data->topic . "\n";
        $msg .= "Thông tin chính: " . $data->mainInformation . "\n";
        if (!empty($data->targetAudience)) {
            $msg .= "Khách hàng mục tiêu: " . $data->targetAudience . "\n";
        }
        $msg .= "Giọng văn: " . $toneMap[$data->tone] . "\n";
        $msg .= "Độ dài: " . $lengthMap[$data->length] . "\n";
        
        if (!empty($data->requiredKeywords)) {
            $msg .= "Từ khóa bắt buộc: " . implode(", ", $data->requiredKeywords) . "\n";
        }
        if (!empty($data->excludedContent)) {
            $msg .= "Nội dung cần tránh: " . implode(", ", $data->excludedContent) . "\n";
        }
        
        return $msg;
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
