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
            if (!Schema::hasColumn('posts', 'image_prompt')) {
                $table->text('image_prompt')->nullable();
                $table->string('image_path')->nullable();
                $table->string('final_image_path')->nullable();
                $table->string('facebook_page_id')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->string('timezone')->default('Asia/Ho_Chi_Minh')->nullable();
                $table->string('facebook_post_id')->nullable();
                $table->text('generation_error')->nullable();
                $table->text('publish_error')->nullable();
                $table->integer('retry_count')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'image_prompt',
                'image_path',
                'final_image_path',
                'facebook_page_id',
                'scheduled_at',
                'timezone',
                'facebook_post_id',
                'generation_error',
                'publish_error',
                'retry_count',
            ]);
        });
    }
};
