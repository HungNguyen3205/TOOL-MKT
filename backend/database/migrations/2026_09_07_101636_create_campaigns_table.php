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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->foreignId('brand_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('social_posting'); // social_posting, content_rotation
            $table->string('status')->default('draft'); // draft, running, paused, completed
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->json('settings')->nullable(); // For schedule slots, auto-distribute etc.
            $table->timestamps();
        });

        // Also add campaign_id to posts table
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropColumn('campaign_id');
        });
        Schema::dropIfExists('campaigns');
    }
};
