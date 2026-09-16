<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\FacebookPage;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BulkImportTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $brand;
    protected $page;
    protected $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->brand = Brand::factory()->create();
        $this->page = FacebookPage::create([
            'brand_id' => $this->brand->id,
            'page_id' => '1234567890',
            'page_name' => 'Test Page',
            'access_token' => 'test_token',
            'connection_status' => 'connected',
            'workspace_id' => 1
        ]);
        
        $this->actingAs($this->user);
    }

    protected function createCsvFile(array $data, string $filename = 'test.csv')
    {
        $content = implode(',', ['STT', 'title', 'content', 'cta', 'hashtags', 'image', 'publish_date', 'publish_time']) . "\n";
        foreach ($data as $row) {
            // Need to wrap content in quotes to allow commas
            $row[1] = '"' . str_replace('"', '""', $row[1]) . '"'; // title
            $row[2] = '"' . str_replace('"', '""', $row[2]) . '"'; // content
            $content .= implode(',', $row) . "\n";
        }
        
        return UploadedFile::fake()->createWithContent($filename, $content);
    }

    public function test_excel_mode_with_schedule()
    {
        // 1. Excel có sẵn lịch đăng.
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        $file = $this->createCsvFile([
            [1, 'T1', 'C1', '', '', '', $tomorrow, '08:00']
        ]);

        $response = $this->postJson('/api/imports/preview', [
            'file' => $file,
            'workspace_id' => 1,
            'brand_id' => $this->brand->id,
            'facebook_page_id' => $this->page->page_id,
            'schedule_mode' => 'excel',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.rows.0.is_valid'));
        $this->assertEquals($tomorrow, $response->json('data.rows.0.publish_date'));
    }

    public function test_excel_mode_missing_date_or_time()
    {
        // 2 & 3. Excel thiếu ngày / thiếu giờ
        $file = $this->createCsvFile([
            [1, 'T1', 'C1', '', '', '', '', '08:00'], // Missing date
            [2, 'T2', 'C2', '', '', '', '2026-09-10', '']  // Missing time
        ]);

        $response = $this->postJson('/api/imports/preview', [
            'file' => $file,
            'workspace_id' => 1,
            'schedule_mode' => 'excel',
        ]);

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.rows.0.is_valid'));
        $this->assertContains('Thiếu ngày/giờ đăng', $response->json('data.rows.0.errors'));
        $this->assertFalse($response->json('data.rows.1.is_valid'));
    }

    public function test_past_time_validation()
    {
        // 4. Thời gian trong quá khứ
        $yesterday = Carbon::now()->subDay()->format('Y-m-d');
        $file = $this->createCsvFile([
            [1, 'T1', 'C1', '', '', '', $yesterday, '08:00']
        ]);

        $response = $this->postJson('/api/imports/preview', [
            'file' => $file,
            'workspace_id' => 1,
            'schedule_mode' => 'excel',
        ]);

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.rows.0.is_valid'));
        $this->assertContains('Thời gian đăng trong quá khứ', $response->json('data.rows.0.errors'));
    }

    public function test_tool_mode_1_post_per_day()
    {
        // 5. Chế độ 1 bài/ngày
        $file = $this->createCsvFile([
            [1, 'T1', 'C1', '', '', '', '', ''],
            [2, 'T2', 'C2', '', '', '', '', '']
        ]);
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        $dayAfter = Carbon::now()->addDays(2)->format('Y-m-d');

        $response = $this->postJson('/api/imports/preview', [
            'file' => $file,
            'workspace_id' => 1,
            'schedule_mode' => 'tool',
            'start_date' => $tomorrow,
            'frequency' => 1,
            'times' => ['08:00']
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.rows.0.is_valid'));
        $this->assertEquals($tomorrow, $response->json('data.rows.0.publish_date'));
        $this->assertEquals('08:00', $response->json('data.rows.0.publish_time'));
        
        $this->assertTrue($response->json('data.rows.1.is_valid'));
        $this->assertEquals($dayAfter, $response->json('data.rows.1.publish_date')); // Moves to next day
        $this->assertEquals('08:00', $response->json('data.rows.1.publish_time'));
    }

    public function test_tool_mode_2_posts_per_day()
    {
        // 6. Chế độ 2 bài/ngày với hai khung giờ.
        $file = $this->createCsvFile([
            [1, 'T1', 'C1', '', '', '', '', ''],
            [2, 'T2', 'C2', '', '', '', '', ''],
            [3, 'T3', 'C3', '', '', '', '', '']
        ]);
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');

        $response = $this->postJson('/api/imports/preview', [
            'file' => $file,
            'workspace_id' => 1,
            'schedule_mode' => 'tool',
            'start_date' => $tomorrow,
            'frequency' => 2,
            'times' => ['08:00', '19:00']
        ]);

        $response->assertStatus(200);
        $this->assertEquals($tomorrow, $response->json('data.rows.0.publish_date'));
        $this->assertEquals('08:00', $response->json('data.rows.0.publish_time'));
        
        $this->assertEquals($tomorrow, $response->json('data.rows.1.publish_date'));
        $this->assertEquals('19:00', $response->json('data.rows.1.publish_time'));
        
        $dayAfter = Carbon::now()->addDays(2)->format('Y-m-d');
        $this->assertEquals($dayAfter, $response->json('data.rows.2.publish_date'));
        $this->assertEquals('08:00', $response->json('data.rows.2.publish_time'));
    }

    public function test_duplicate_schedule_same_page()
    {
        // 9. Hai khung giờ bị trùng / 10. Bài bị trùng
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        
        // Setup an existing post
        Post::factory()->create([
            'brand_id' => $this->brand->id,
            'facebook_page_id' => $this->page->page_id,
            'scheduled_at' => Carbon::parse($tomorrow . ' 08:00', 'Asia/Ho_Chi_Minh')->utc(),
            'status' => 'scheduled'
        ]);

        $file = $this->createCsvFile([
            [1, 'T1', 'C1', '', '', '', $tomorrow, '08:00']
        ]);

        $response = $this->postJson('/api/imports/preview', [
            'file' => $file,
            'workspace_id' => 1,
            'brand_id' => $this->brand->id,
            'facebook_page_id' => $this->page->page_id,
            'schedule_mode' => 'excel',
        ]);

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.rows.0.is_valid'));
        $this->assertContains('Trùng lịch đăng trên Page này', $response->json('data.rows.0.errors'));
    }

    public function test_import_with_image_and_draft_mode()
    {
        // 12. Import có ảnh / Chỉ lưu draft
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        $file = $this->createCsvFile([
            [1, 'T1', 'C1', '', '', 'https://example.com/img.jpg', $tomorrow, '08:00']
        ]);

        $preview = $this->postJson('/api/imports/preview', [
            'file' => $file,
            'workspace_id' => 1,
            'brand_id' => $this->brand->id,
            'facebook_page_id' => $this->page->page_id,
            'schedule_mode' => 'excel',
        ]);

        $batchId = $preview->json('data.batch_id');
        $rows = $preview->json('data.rows');

        $confirm = $this->postJson('/api/imports/confirm', [
            'batch_id' => $batchId,
            'rows' => $rows,
            'action_on_duplicate' => 'save_draft'
        ]);

        $confirm->assertStatus(200);
        $this->assertDatabaseHas('posts', [
            'title' => 'T1',
            'status' => 'draft',
            'scheduled_at' => null
        ]);
    }

}
