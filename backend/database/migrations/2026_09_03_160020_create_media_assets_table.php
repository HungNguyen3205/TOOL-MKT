<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id')->nullable()->index();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            
            $table->string('type'); // image, video
            $table->string('status')->default('processing'); // processing, ready, failed
            $table->string('disk')->default('public');
            $table->string('path');
            
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime_type');
            $table->string('extension')->nullable();
            $table->unsignedBigInteger('size_bytes');
            
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration_seconds')->nullable();
            
            $table->string('checksum')->index();
            
            $table->string('title')->nullable();
            $table->text('alt_text')->nullable();
            $table->text('caption')->nullable();
            
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            
            $table->timestamp('processed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
