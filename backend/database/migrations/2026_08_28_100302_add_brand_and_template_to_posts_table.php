<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Drop existing brand_id and recreate it as foreign key if necessary,
            // or just ensure it exists and add content_template_id
            if (!Schema::hasColumn('posts', 'content_template_id')) {
                $table->unsignedBigInteger('content_template_id')->nullable()->after('brand_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('content_template_id');
        });
    }
};
