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
        Schema::create('comment_publication_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_comment_id')->constrained()->cascadeOnDelete();
            $table->integer('attempt_number');
            $table->string('status'); // success, failed
            $table->integer('http_status')->nullable();
            $table->string('facebook_error_code')->nullable();
            $table->string('facebook_error_subcode')->nullable();
            $table->text('message')->nullable();
            $table->json('response_meta')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_publication_attempts');
    }
};
