<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('objective');
            $table->text('opening_style')->nullable();
            $table->json('body_structure')->nullable();
            $table->text('cta_instruction')->nullable();
            $table->text('hashtag_instruction')->nullable();
            $table->text('additional_instruction')->nullable();
            $table->text('example_content')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['brand_id', 'objective', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_templates');
    }
};
