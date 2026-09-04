<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_generation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('content_template_id')->nullable();
            $table->string('provider')->index(); // openai, ollama
            $table->string('model')->nullable();
            $table->string('prompt_version')->nullable();
            $table->string('prompt_hash')->nullable();
            $table->json('request_data')->nullable(); // Có thể config ẩn nếu nhạy cảm
            $table->integer('number_of_versions')->default(1);
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->decimal('estimated_cost', 10, 6)->nullable();
            $table->string('currency')->default('USD')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->decimal('quality_score_average', 5, 2)->nullable();
            $table->boolean('successful')->default(false)->index();
            $table->string('error_code')->nullable();
            $table->boolean('retried')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_generation_logs');
    }
};
