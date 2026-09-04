<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->integer('quality_score')->nullable();
            $table->string('quality_status')->nullable();
            $table->json('quality_result')->nullable();
            $table->timestamp('quality_checked_at')->nullable();
            
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            
            $table->integer('content_version')->default(1);
            $table->string('last_content_hash')->nullable();
            $table->text('review_note')->nullable();
            
            $table->unsignedBigInteger('last_edited_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();

            $table->index('quality_status');
        });

        // Migrate existing ready posts
        DB::table('posts')->where('status', 'ready')->update([
            'ready_at' => DB::raw('updated_at'),
            'approved_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'quality_score', 'quality_status', 'quality_result', 'quality_checked_at',
                'submitted_for_review_at', 'approved_at', 'ready_at',
                'content_version', 'last_content_hash', 'review_note',
                'last_edited_by', 'approved_by'
            ]);
        });
    }
};
