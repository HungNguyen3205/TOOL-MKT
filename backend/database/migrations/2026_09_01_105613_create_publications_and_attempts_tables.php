<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('publication_logs');

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('facebook_page_id');
            $table->string('platform')->default('facebook');
            $table->string('publication_type')->default('text');
            $table->string('status')->default('queued'); // queued, processing, published, failed, cancelled
            $table->json('content_snapshot')->nullable();
            $table->string('content_hash')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('requested_by')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('external_post_id')->nullable();
            $table->text('external_post_url')->nullable();
            $table->integer('attempts_count')->default(0);
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->index('post_id');
            $table->index('facebook_page_id');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('publication_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('publication_id');
            $table->integer('attempt_number');
            $table->string('status'); // processing, success, failed
            $table->integer('http_status')->nullable();
            $table->string('platform_error_code')->nullable();
            $table->string('platform_error_subcode')->nullable();
            $table->string('error_category')->nullable();
            $table->text('error_message')->nullable();
            $table->json('response_metadata')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('publication_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_attempts');
        Schema::dropIfExists('publications');
        
        // Recreate the old table just in case
        Schema::create('publication_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('facebook_page_id');
            $table->string('action')->default('publish_now');
            $table->string('status'); 
            $table->string('request_type'); 
            $table->string('facebook_post_id')->nullable();
            $table->text('facebook_post_url')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('http_status')->nullable();
            $table->json('response_metadata')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }
};
