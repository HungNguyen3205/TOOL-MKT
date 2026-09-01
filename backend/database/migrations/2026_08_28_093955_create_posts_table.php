<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('title');
            $table->text('content');
            $table->text('cta')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('objective')->nullable();
            $table->string('tone')->nullable();
            $table->string('content_length')->nullable();
            $table->string('source')->default('manual');
            $table->string('status')->default('draft');
            $table->string('ai_model')->nullable();
            $table->string('ai_provider')->nullable();
            $table->json('source_input')->nullable();
            $table->integer('selected_version')->nullable();
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('source');
            $table->index('created_at');
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
