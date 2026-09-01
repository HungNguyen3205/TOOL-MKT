<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_id')->unique();
            $table->string('page_name');
            $table->string('page_username')->nullable();
            $table->text('page_picture_url')->nullable();
            $table->text('access_token'); // Encrypted at model level
            $table->timestamp('token_expires_at')->nullable();
            $table->json('granted_scopes')->nullable();
            $table->timestamp('permissions_checked_at')->nullable();
            $table->string('connected_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('connection_status')->default('connected'); // connected, token_expired, permission_missing, disconnected, error
            $table->timestamp('last_verified_at')->nullable();
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('is_active');
            $table->index('connection_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_pages');
    }
};
