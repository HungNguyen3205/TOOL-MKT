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
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->integer('import_row_number')->nullable();
            $table->string('import_fingerprint')->nullable();
            
            $table->index('import_fingerprint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
            $table->dropIndex(['import_fingerprint']);
            $table->dropColumn(['import_batch_id', 'import_row_number', 'import_fingerprint']);
        });
    }
};
