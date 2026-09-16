<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facebook_page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_comment_id')->nullable()->constrained('post_comments')->nullOnDelete();
            
            $table->string('source')->default('tool'); // tool or facebook
            $table->text('content');
            $table->string('status')->default('draft'); // draft, scheduled, publishing, published, failed, cancelled
            
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('published_at')->nullable();
            
            $table->string('facebook_comment_id')->nullable()->unique();
            $table->string('facebook_comment_url')->nullable();
            
            $table->integer('attempt_count')->default(0);
            $table->dateTime('last_attempt_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            
            $table->string('schedule_type')->default('absolute'); // absolute, after_post
            $table->integer('delay_minutes')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_comments');
    }
};
