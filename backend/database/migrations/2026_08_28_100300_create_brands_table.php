<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('industry')->nullable();
            $table->text('description')->nullable();
            $table->text('products_services')->nullable();
            $table->text('target_audience')->nullable();
            $table->string('tone')->nullable();
            $table->string('slogan')->nullable();
            $table->text('default_cta')->nullable();
            $table->json('default_hashtags')->nullable();
            $table->json('required_keywords')->nullable();
            $table->json('prohibited_terms')->nullable();
            $table->json('writing_rules')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('slug');
            $table->index('is_default');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
