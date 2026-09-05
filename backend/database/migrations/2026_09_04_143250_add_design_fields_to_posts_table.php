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
            $table->string('design_format')->nullable();
            $table->string('design_title')->nullable();
            $table->string('design_layout')->nullable();
            $table->string('design_visual')->nullable();
            $table->string('design_color')->nullable();
            $table->text('design_suggestion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'design_format',
                'design_title',
                'design_layout',
                'design_visual',
                'design_color',
                'design_suggestion'
            ]);
        });
    }
};
