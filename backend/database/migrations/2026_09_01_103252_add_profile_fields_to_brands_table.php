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
            $table->string('brand_type')->default('other')->after('slug');
            $table->string('website')->nullable()->after('industry');
            $table->string('hotline')->nullable()->after('website');
            $table->string('email')->nullable()->after('hotline');
            $table->text('address')->nullable()->after('email');
            $table->json('service_areas')->nullable()->after('address');
            $table->text('positioning')->nullable()->after('description');
            $table->text('unique_value_proposition')->nullable()->after('positioning');
            $table->text('brand_story')->nullable()->after('slogan');
            $table->string('brand_personality')->nullable()->after('brand_story');
            $table->json('competitive_advantages')->nullable()->after('brand_personality');
            $table->json('customer_pain_points')->nullable()->after('target_audience');
            $table->json('customer_desires')->nullable()->after('customer_pain_points');
            $table->json('customer_objections')->nullable()->after('customer_desires');
            $table->string('default_language')->nullable()->after('writing_rules');
            $table->integer('emoji_limit')->nullable()->after('default_language');
            $table->string('preferred_addressing')->nullable()->after('emoji_limit');
            $table->json('platform_rules')->nullable()->after('preferred_addressing');
            $table->integer('profile_completeness')->default(0)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn([
                'brand_type', 'website', 'hotline', 'email', 'address', 'service_areas',
                'positioning', 'unique_value_proposition', 'brand_story', 'brand_personality',
                'competitive_advantages', 'customer_pain_points', 'customer_desires', 'customer_objections',
                'default_language', 'emoji_limit', 'preferred_addressing', 'platform_rules', 'profile_completeness'
            ]);
        });
    }
};
