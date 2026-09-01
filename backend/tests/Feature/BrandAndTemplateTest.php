<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Brand;
use App\Models\ContentTemplate;

class BrandAndTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_brands()
    {
        Brand::factory()->count(3)->create();
        $response = $this->getJson('/api/brands');
        $response->assertStatus(200)->assertJsonCount(3, 'data');
    }

    public function test_can_create_brand()
    {
        $payload = [
            'name' => 'Test Brand',
            'industry' => 'Tech',
            'is_active' => true,
        ];
        
        $response = $this->postJson('/api/brands', $payload);
        $response->assertStatus(200)->assertJsonPath('data.name', 'Test Brand');
        $this->assertDatabaseHas('brands', ['name' => 'Test Brand', 'slug' => 'test-brand']);
    }

    public function test_cannot_create_brand_without_name()
    {
        $response = $this->postJson('/api/brands', ['industry' => 'Tech']);
        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_slug_must_be_unique()
    {
        Brand::factory()->create(['slug' => 'test-slug']);
        $response = $this->postJson('/api/brands', ['name' => 'Another', 'slug' => 'test-slug']);
        $response->assertStatus(422)->assertJsonValidationErrors(['slug']);
    }

    public function test_can_update_brand_without_slug_conflict()
    {
        $brand = Brand::factory()->create(['name' => 'Old Name', 'slug' => 'test']);
        $response = $this->putJson("/api/brands/{$brand->id}", [
            'name' => 'New Name',
            'slug' => 'test'
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'name' => 'New Name']);
    }

    public function test_can_set_default_brand()
    {
        $b1 = Brand::factory()->create(['is_default' => true]);
        $b2 = Brand::factory()->create(['is_default' => false]);

        $response = $this->patchJson("/api/brands/{$b2->id}/default");
        $response->assertStatus(200);

        $this->assertFalse(Brand::find($b1->id)->is_default);
        $this->assertTrue(Brand::find($b2->id)->is_default);
    }
    
    public function test_cannot_set_inactive_brand_as_default()
    {
        $b = Brand::factory()->create(['is_active' => false]);
        $response = $this->patchJson("/api/brands/{$b->id}/default");
        $response->assertStatus(400);
    }

    public function test_can_create_template()
    {
        $brand = Brand::factory()->create();
        $payload = [
            'name' => 'Promo',
            'objective' => 'promotion',
            'body_structure' => ['Part 1', 'Part 2']
        ];
        
        $response = $this->postJson("/api/brands/{$brand->id}/templates", $payload);
        $response->assertStatus(200);
        $this->assertDatabaseHas('content_templates', ['name' => 'Promo', 'brand_id' => $brand->id]);
    }
    
    public function test_can_set_default_template_per_objective()
    {
        $brand = Brand::factory()->create();
        $t1 = ContentTemplate::factory()->create(['brand_id' => $brand->id, 'objective' => 'sales', 'is_default' => true]);
        $t2 = ContentTemplate::factory()->create(['brand_id' => $brand->id, 'objective' => 'sales', 'is_default' => false]);
        $t3 = ContentTemplate::factory()->create(['brand_id' => $brand->id, 'objective' => 'event', 'is_default' => false]);
        
        $response = $this->patchJson("/api/brands/{$brand->id}/templates/{$t2->id}/default");
        $response->assertStatus(200);
        
        $this->assertFalse(ContentTemplate::find($t1->id)->is_default);
        $this->assertTrue(ContentTemplate::find($t2->id)->is_default);
        $this->assertFalse(ContentTemplate::find($t3->id)->is_default); // Unaffected
    }

    public function test_generate_content_with_brand_and_template()
    {
        // Not calling actual AI here unless mocked. We can test validation.
        $brand = Brand::factory()->create();
        $template = ContentTemplate::factory()->create(['brand_id' => $brand->id, 'objective' => 'sales']);
        
        $payload = [
            'brand_id' => $brand->id,
            'content_template_id' => $template->id,
            'topic' => 'Test',
            'main_information' => 'Test',
            'objective' => 'introduction', // Mismatch objective!
            'tone' => 'friendly',
            'length' => 'short',
            'number_of_versions' => 1
        ];

        $response = $this->postJson('/api/content/generate', $payload);
        $response->assertStatus(400)->assertJsonPath('error_code', 'TEMPLATE_OBJECTIVE_MISMATCH');
    }
}
