<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Brand;
use App\Models\User;

class BrandUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Since no auth is fully required for API yet, or maybe it is?
        // Let's create a user if auth is required
        $this->user = User::factory()->create();
    }

    public function test_create_brand_only_name()
    {
        $response = $this->actingAs($this->user)->postJson('/api/brands', [
            'name' => 'Minimal Brand'
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);

        $this->assertDatabaseHas('brands', [
            'name' => 'Minimal Brand',
            'slug' => 'minimal-brand'
        ]);
    }

    public function test_create_brand_full_fields()
    {
        $payload = [
            'name' => 'Full Brand',
            'industry' => 'Tech',
            'website' => 'example.com',
            'hotline' => '123456789',
            'email' => 'test@example.com',
            'service_areas' => ['Hanoi', 'HCM'],
            'is_default' => true,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/brands', $payload);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('brands', [
            'name' => 'Full Brand',
            'website' => 'https://example.com'
        ]);
    }

    public function test_create_brand_empty_json_fields()
    {
        $response = $this->actingAs($this->user)->postJson('/api/brands', [
            'name' => 'Empty JSON',
            'service_areas' => []
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('brands', [
            'name' => 'Empty JSON'
        ]);
        
        $brand = Brand::where('name', 'Empty JSON')->first();
        $this->assertNull($brand->service_areas);
    }

    public function test_update_brand()
    {
        $brand = Brand::factory()->create(['name' => 'Old Name']);
        
        $response = $this->actingAs($this->user)->putJson('/api/brands/' . $brand->id, [
            'name' => 'New Name'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'New Name'
        ]);
        
        // Check brand versions
        $this->assertDatabaseHas('brand_versions', [
            'brand_id' => $brand->id,
            'version_number' => 1
        ]);
    }

    public function test_duplicate_slug_returns_error()
    {
        Brand::factory()->create(['name' => 'Duplicate', 'slug' => 'duplicate']);

        $response = $this->actingAs($this->user)->postJson('/api/brands', [
            'name' => 'Duplicate',
            'slug' => 'duplicate'
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false)
                 ->assertJsonPath('error_code', 'VALIDATION_FAILED');
    }
}
