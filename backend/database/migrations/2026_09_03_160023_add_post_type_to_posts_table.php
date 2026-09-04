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
            if (!Schema::hasColumn('posts', 'post_type')) {
                $table->string('post_type')
                    ->default('text')
                    ->after('status');
            }
        });

        // Ensure all old posts are updated to 'text' to avoid any nulls
        DB::table('posts')->whereNull('post_type')->update(['post_type' => 'text']);
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'post_type')) {
                $table->dropColumn('post_type');
            }
        });
    }
};
