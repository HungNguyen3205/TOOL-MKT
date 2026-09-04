<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->integer('version_number');
            $table->string('title');
            $table->text('content');
            $table->text('cta')->nullable();
            $table->json('hashtags')->nullable();
            
            $table->string('content_hash');
            $table->integer('quality_score')->nullable();
            $table->string('quality_status')->nullable();
            $table->json('quality_result')->nullable();
            
            $table->string('change_source');
            $table->string('change_summary')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            
            $table->timestamps();

            $table->index(['post_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_versions');
    }
};
