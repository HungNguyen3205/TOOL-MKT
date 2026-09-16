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
        Schema::create('scheduled_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('facebook_page_id')->index();
            $table->unsignedBigInteger('publication_id')->index()->comment('Liên kết tới post đã đăng');
            $table->text('message');
            $table->string('image_path')->nullable();
            $table->timestamp('scheduled_at');
            $table->string('status')->default('queued')->comment('queued, processing, published, failed');
            $table->string('external_comment_id')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_comments');
    }
};
