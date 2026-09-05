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
            if (!Schema::hasColumn('posts', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('posts', 'facebook_post_id')) {
                $table->string('facebook_post_id')->nullable()->after('scheduled_at');
            }
            if (!Schema::hasColumn('posts', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('facebook_post_id');
            }
            if (!Schema::hasColumn('posts', 'publish_error')) {
                $table->text('publish_error')->nullable()->after('published_at');
            }
            if (!Schema::hasColumn('posts', 'retry_count')) {
                $table->integer('retry_count')->default(0)->after('publish_error');
            }
            if (!Schema::hasColumn('posts', 'facebook_page_id')) {
                $table->string('facebook_page_id')->nullable()->after('brand_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            //
        });
    }
};
