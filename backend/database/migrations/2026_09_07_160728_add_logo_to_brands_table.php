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
        Schema::table('brands', function (Blueprint $table) {
            $table->string('logo_path')->nullable();
            $table->string('logo_original_name')->nullable();
            $table->string('logo_mime_type')->nullable();
            $table->integer('logo_size')->nullable();
            $table->timestamp('logo_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path',
                'logo_original_name',
                'logo_mime_type',
                'logo_size',
                'logo_updated_at'
            ]);
        });
    }
};
