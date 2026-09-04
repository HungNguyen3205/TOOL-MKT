<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('media_asset_id');
            
            $table->string('variant_type'); // thumbnail, preview, optimized, poster_frame
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            
            $table->json('metadata')->nullable();
            
            $table->timestamps();

            // Foreign key, do not cascade delete to prevent accidental data loss. Application logic handles deletion.
            $table->foreign('media_asset_id')->references('id')->on('media_assets')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
    }
};
