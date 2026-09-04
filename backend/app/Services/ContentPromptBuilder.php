<?php

namespace App\Services;

use App\DTOs\ContentGenerationData;
use App\Models\BrandKnowledgeItem;

class ContentPromptBuilder
{
    public function build(ContentGenerationData $data): array
    {
        $version = config('services.ai_quality.prompt_version', 'brand_prompt_v3');
        $systemMsg = $this->buildSystemInstruction($data);
        $userMsg = $this->buildUserMessage($data);

        $fullPromptString = $systemMsg . "\n\n" . $userMsg;
        $hash = md5($fullPromptString);

        return [
            'version' => $version,
            'hash' => $hash,
            'system' => $systemMsg,
            'user' => $userMsg,
            'full_prompt' => $fullPromptString,
            'knowledge_items_used' => $data->knowledgeItems,
        ];
    }

    private function buildSystemInstruction(ContentGenerationData $data): string
    {
        $prompt = "Bạn là một chuyên gia sáng tạo nội dung mạng xã hội chuyên nghiệp. Nhiệm vụ của bạn là viết bài đăng Facebook bằng Tiếng Việt.\n\n";

        // 1. BRAND IDENTITY
        if ($data->brand) {
            $prompt .= "--- 1. BRAND IDENTITY (NHẬN DIỆN THƯƠNG HIỆU) ---\n";
            $prompt .= "- Tên thương hiệu: " . $data->brand->name . "\n";
            if ($data->brand->brand_type) $prompt .= "- Loại mô hình kinh doanh: " . $data->brand->brand_type . " (Lưu ý: Bạn phải viết nội dung phù hợp với mô hình này)\n";
            if ($data->brand->industry) $prompt .= "- Ngành nghề: " . $data->brand->industry . "\n";
            if ($data->brand->description) $prompt .= "- Mô tả chung: " . $data->brand->description . "\n";
            if ($data->brand->products_services) $prompt .= "- Sản phẩm/dịch vụ cốt lõi: " . $data->brand->products_services . "\n";
            if ($data->brand->positioning) $prompt .= "- Định vị thương hiệu: " . $data->brand->positioning . "\n";
            if ($data->brand->slogan) $prompt .= "- Slogan: " . $data->brand->slogan . "\n";
            $prompt .= "------------------------------------------------\n\n";
        }

        // 6. WRITING RULES
        $prompt .= "--- 6. WRITING RULES (QUY TẮC VIẾT) ---\n";
        $prompt .= "- CHỈ VIẾT BẰNG TIẾNG VIỆT.\n";
        $prompt .= "- Phù hợp để đăng lên Facebook Page (sử dụng format phù hợp, giãn dòng tốt).\n";
        
        if ($data->brand) {
            if ($data->brand->tone) $prompt .= "- Giọng văn chung của thương hiệu: " . $data->brand->tone . "\n";
            if ($data->brand->brand_personality) $prompt .= "- Tính cách thương hiệu: " . $data->brand->brand_personality . "\n";
            if ($data->brand->emoji_limit !== null) $prompt .= "- Giới hạn emoji: Tối đa " . $data->brand->emoji_limit . " emoji mỗi bài.\n";
            else $prompt .= "- Giới hạn emoji: Tối đa 3-5 emoji mỗi bài.\n";
            
            if (!empty($data->brand->writing_rules)) {
                $prompt .= "- Các quy tắc viết riêng:\n  + " . implode("\n  + ", $data->brand->writing_rules) . "\n";
            }
        } else {
            $prompt .= "- KHÔNG lạm dụng emoji (tối đa 3-5 emoji mỗi bài).\n";
        }
        $prompt .= "------------------------------------------\n\n";

        // 7. PROHIBITED CONTENT
        $prompt .= "--- 7. PROHIBITED CONTENT (NỘI DUNG CẤM) ---\n";
        $prompt .= "- KHÔNG tự tạo giá bán, số liệu, ưu đãi, thành phần, địa chỉ, hoặc hotline mà người dùng chưa cung cấp.\n";
        $prompt .= "- KHÔNG cam kết hiệu quả không có căn cứ. KHÔNG so sánh với đối thủ nếu không được yêu cầu.\n";
        
        $excluded = $data->excludedContent ?? [];
        if (!empty($excluded)) {
            $prompt .= "- TUYỆT ĐỐI KHÔNG SỬ DỤNG CÁC TỪ HOẶC NỘI DUNG SAU:\n  + " . implode("\n  + ", $excluded) . "\n";
        }
        $prompt .= "--------------------------------------------\n\n";

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

        // 2. VERIFIED BRAND KNOWLEDGE
        if (!empty($data->knowledgeItems) && $data->brand) {
            $knowledge = BrandKnowledgeItem::where('brand_id', $data->brand->id)
                ->whereIn('id', $data->knowledgeItems)
                ->where('is_active', true)
                ->get();
                
            if ($knowledge->isNotEmpty()) {
                $msg .= "--- 2. VERIFIED BRAND KNOWLEDGE (KIẾN THỨC ĐÃ XÁC MINH) ---\n";
                $msg .= "Sử dụng các thông tin sau nếu cần thiết cho nội dung bài viết:\n";
                foreach ($knowledge as $k) {
                    $msg .= "- [{$k->title}]: {$k->content}\n";
                }
                $msg .= "---------------------------------------------------------\n\n";
            }
        }

        // 3. CONTENT TEMPLATE
        if ($data->template) {
            $msg .= "--- 3. CONTENT TEMPLATE (MẪU NỘI DUNG) ---\n";
            $msg .= "- Mục tiêu: " . ($objectiveMap[$data->template->objective] ?? $data->template->objective) . "\n";
            if ($data->template->opening_style) $msg .= "- Phong cách mở bài: " . $data->template->opening_style . "\n";
            if (!empty($data->template->body_structure)) {
                $msg .= "- Cấu trúc thân bài:\n  + " . implode("\n  + ", $data->template->body_structure) . "\n";
            }
            if ($data->template->additional_instruction) $msg .= "- Chỉ dẫn bổ sung: " . $data->template->additional_instruction . "\n";
            $msg .= "------------------------------------------\n\n";
        }

        // 4. USER INPUT
        $msg .= "--- 4. USER INPUT (YÊU CẦU CỤ THỂ CHO BÀI VIẾT NÀY) ---\n";
        $msg .= "- Chủ đề/Sản phẩm: " . $data->topic . "\n";
        $msg .= "- Thông tin chính (Hãy đưa vào bài): " . $data->mainInformation . "\n";
        
        if (!empty($data->targetAudience)) {
            $msg .= "- Khách hàng mục tiêu: " . $data->targetAudience . "\n";
        }
        
        if ($data->tone && isset($toneMap[$data->tone])) {
            $msg .= "- Giọng văn yêu cầu cho bài này: " . $toneMap[$data->tone] . "\n";
        }
        
        $msg .= "- Độ dài mong muốn: " . ($lengthMap[$data->length] ?? $data->length) . "\n";
        $msg .= "- Số phiên bản cần tạo: " . $data->numberOfVersions . " phiên bản khác biệt nhau.\n";
        
        if (!empty($data->requiredKeywords)) {
            $msg .= "- Từ khóa BẮT BUỘC phải có: " . implode(", ", $data->requiredKeywords) . "\n";
        }
        
        if (!empty($data->ctaInstruction)) {
            $msg .= "- Hướng dẫn Call To Action (CTA): " . $data->ctaInstruction . "\n";
        }
        
        if (!empty($data->hashtagInstruction)) {
            $msg .= "- Hướng dẫn Hashtag (mỗi hashtag viết liền): " . $data->hashtagInstruction . "\n";
        }
        $msg .= "--------------------------------------------------------\n\n";

        // 5. CONTACT INFORMATION
        if ($data->useContactInfo && $data->brand) {
            $contact = [];
            if ($data->brand->hotline) $contact[] = "Hotline: " . $data->brand->hotline;
            if ($data->brand->website) $contact[] = "Website: " . $data->brand->website;
            if ($data->brand->address) $contact[] = "Địa chỉ: " . $data->brand->address;
            
            if (!empty($contact)) {
                $msg .= "--- 5. CONTACT INFORMATION (THÔNG TIN LIÊN HỆ) ---\n";
                $msg .= "- Bạn phải dùng chính xác thông tin liên hệ này ở phần CTA hoặc cuối bài:\n";
                $msg .= implode("\n", $contact) . "\n";
                $msg .= "--------------------------------------------------\n\n";
            }
        }

        // 8. OUTPUT SCHEMA
        $msg .= "--- 8. OUTPUT SCHEMA (ĐỊNH DẠNG ĐẦU RA) ---\n";
        $msg .= "TRẢ VỀ DUY NHẤT ĐỊNH DẠNG JSON. KHÔNG SỬ DỤNG MARKDOWN CODE FENCE (```json).\n";
        $msg .= "JSON phải có cấu trúc chính xác như sau:\n";
        $msg .= '{"versions":[{"title":"Tiêu đề bài viết","content":"Nội dung chính","cta":"Lời kêu gọi hành động","hashtags":["#Hashtag1","#Hashtag2"]}]}';
        $msg .= "\n-------------------------------------------\n";

        return $msg;
    }
}
