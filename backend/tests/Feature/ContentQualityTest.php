<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Brand;
use App\Services\ContentQualityValidator;
use App\DTOs\ContentGenerationData;

class ContentQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_quality_validator_detects_missing_keywords()
    {
        $validator = new ContentQualityValidator();
        $data = new ContentGenerationData(
            topic: 'Yoga',
            mainInformation: 'Lớp mới',
            objective: 'sales',
            tone: 'friendly',
            length: 'medium',
            numberOfVersions: 1,
            requiredKeywords: ['yoga cơ bản', 'giảm cân']
        );

        $versions = [
            [
                'title' => 'Lớp Yoga mới',
                'content' => 'Hãy tham gia lớp yoga của chúng tôi.',
                'cta' => 'Đăng ký ngay!',
                'hashtags' => ['#yoga']
            ]
        ];

        $result = $validator->validateVersions($versions, $data);
        
        $this->assertCount(2, $result[0]['quality']['missing_keywords']);
        $this->assertEquals('passed', $result[0]['quality']['status']);
    }

    public function test_quality_validator_detects_prohibited_terms()
    {
        $validator = new ContentQualityValidator();
        $data = new ContentGenerationData(
            topic: 'Yoga',
            mainInformation: 'Lớp mới',
            objective: 'sales',
            tone: 'friendly',
            length: 'medium',
            numberOfVersions: 1,
            excludedContent: ['chữa bệnh', 'thần dược']
        );

        $versions = [
            [
                'title' => 'Lớp Yoga thần dược',
                'content' => 'Yoga có thể chữa bệnh cho bạn.',
                'cta' => 'Đăng ký ngay!',
                'hashtags' => ['#yoga']
            ]
        ];

        $result = $validator->validateVersions($versions, $data);
        
        $this->assertCount(2, $result[0]['quality']['prohibited_terms_found']);
        $this->assertEquals('failed', $result[0]['quality']['status']);
    }

    public function test_quality_validator_detects_similarity()
    {
        $validator = new ContentQualityValidator();
        $data = new ContentGenerationData(
            topic: 'Yoga',
            mainInformation: 'Lớp mới',
            objective: 'sales',
            tone: 'friendly',
            length: 'medium',
            numberOfVersions: 2
        );

        $versions = [
            [
                'title' => 'Khám phá lớp Yoga mới tại trung tâm',
                'content' => 'Chúng tôi vừa mở một lớp Yoga cơ bản dành cho người mới bắt đầu.',
                'cta' => 'Đăng ký ngay',
                'hashtags' => ['#yoga']
            ],
            [
                'title' => 'Khám phá lớp Yoga mới tại trung tâm',
                'content' => 'Chúng tôi vừa mở một lớp Yoga cơ bản dành cho người mới bắt đầu.',
                'cta' => 'Đăng ký ngay!',
                'hashtags' => ['#yogacoban']
            ]
        ];

        $result = $validator->validateVersions($versions, $data);
        
        $this->assertTrue($result[0]['quality']['similarity_warning']);
        $this->assertTrue($result[1]['quality']['similarity_warning']);
        $this->assertLessThan(100, $result[0]['quality']['score']);
    }

    public function test_quality_validator_detects_suspicious_claims()
    {
        $validator = new ContentQualityValidator();
        $data = new ContentGenerationData(
            topic: 'Yoga',
            mainInformation: 'Lớp mới',
            objective: 'sales',
            tone: 'friendly',
            length: 'medium',
            numberOfVersions: 1
        );

        $versions = [
            [
                'title' => 'Lớp Yoga Mới',
                'content' => 'Cam kết giảm 100% mỡ thừa chỉ với 500.000 VNĐ.',
                'cta' => 'Đăng ký ngay',
                'hashtags' => ['#yoga']
            ]
        ];

        $result = $validator->validateVersions($versions, $data);
        
        $this->assertNotEmpty($result[0]['quality']['suspicious_claims']);
    }

    public function test_quality_validator_fixes_hashtags()
    {
        $validator = new ContentQualityValidator();
        $data = new ContentGenerationData(
            topic: 'Yoga',
            mainInformation: 'Lớp mới',
            objective: 'sales',
            tone: 'friendly',
            length: 'medium',
            numberOfVersions: 1
        );

        $versions = [
            [
                'title' => 'Lớp Yoga Mới',
                'content' => 'Nội dung ngắn gọn.',
                'cta' => 'Đăng ký ngay',
                'hashtags' => ['yoga', '#yoga khoẻ', ' yoga ']
            ]
        ];

        $result = $validator->validateVersions($versions, $data);
        
        $this->assertEquals(['#yoga', '#yogakhoẻ'], $result[0]['hashtags']);
    }
}
