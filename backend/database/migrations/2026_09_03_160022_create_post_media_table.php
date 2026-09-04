<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('media_asset_id');
            
            $table->integer('position')->default(0);
            $table->string('role')->default('primary'); // primary, gallery, video
            
            $table->timestamps();

            $table->unique(['post_id', 'media_asset_id']);
            $table->index('post_id');
            $table->index('media_asset_id');
            
            // Foreign keys using restrict to prevent accidental cascades on critical media
            $table->foreign('post_id')->references('id')->on('posts')->onDelete('restrict');
            $table->foreign('media_asset_id')->references('id')->on('media_assets')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_media');
    }
};
