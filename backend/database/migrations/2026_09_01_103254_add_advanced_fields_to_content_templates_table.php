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
        Schema::table('content_templates', function (Blueprint $table) {
            $table->string('platform')->nullable()->after('objective');
            $table->string('content_type')->nullable()->after('platform');
            $table->string('default_length')->nullable()->after('example_content');
            $table->integer('default_number_of_versions')->default(1)->after('default_length');
            $table->json('required_fields')->nullable()->after('default_number_of_versions');
            $table->integer('usage_count')->default(0)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_templates', function (Blueprint $table) {
            $table->dropColumn(['platform', 'content_type', 'default_length', 'default_number_of_versions', 'required_fields', 'usage_count']);
        });
    }
};
