<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('facebook_page_id');
            $table->string('action')->default('publish_now');
            $table->string('status'); // processing, success, failed
            $table->string('request_type'); // text, photo
            $table->string('facebook_post_id')->nullable();
            $table->text('facebook_post_url')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('http_status')->nullable();
            $table->json('response_metadata')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('post_id');
            $table->index('facebook_page_id');
            $table->index('status');
            $table->index('attempted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_logs');
    }
};
