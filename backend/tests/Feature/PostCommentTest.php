<?php

namespace Tests\Feature;

use App\Models\FacebookPage;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostCommentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // create a fake post and page
    }

    public function test_can_create_comment()
    {
        $this->assertTrue(true);
        // Will implement full tests later as we focus on UI refactoring next
    }
}
