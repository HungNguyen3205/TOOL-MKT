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
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('facebook_page_id')->nullable();
            
            $table->string('original_filename');
            $table->string('file_type'); // docx, xlsx, csv
            $table->string('timezone')->default('Asia/Ho_Chi_Minh');
            
            $table->integer('total_rows')->default(0);
            $table->integer('valid_rows')->default(0);
            $table->integer('invalid_rows')->default(0);
            $table->integer('imported_rows')->default(0);
            
            $table->string('status')->default('pending'); // pending, preview, processing, completed, failed
            $table->text('error_message')->nullable();
            $table->json('options')->nullable(); // For storing default schedules etc.
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
