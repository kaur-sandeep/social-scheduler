<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_user_id');
            $table->string('provider_username')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->text('user_access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['provider', 'provider_user_id', 'user_id'], 'social_account_identity_unique');
            $table->index(['user_id', 'provider', 'status']);
        });

        Schema::create('social_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('page_id');
            $table->string('page_name');
            $table->string('category')->nullable();
            $table->string('profile_image')->nullable();
            $table->text('page_access_token');
            $table->json('permissions')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['provider', 'page_id', 'social_account_id'], 'social_page_identity_unique');
            $table->index(['provider', 'status']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 32);
            $table->text('message');
            $table->string('status', 32)->default('draft');
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('timezone')->default('UTC');
            $table->timestamp('published_at')->nullable();
            $table->string('provider_post_id')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['platform', 'status', 'scheduled_at']);
            $table->index(['user_id', 'status', 'scheduled_at']);
        });

        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('media_type', 16);
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->index(['post_id', 'display_order']);
        });

        Schema::create('post_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('endpoint');
            $table->json('api_request')->nullable();
            $table->json('api_response')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->boolean('success')->default(false);
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['post_id', 'success']);
        });

        Schema::create('failed_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->text('error_message');
            $table->json('context')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamps();
            $table->index(['resolved', 'next_retry_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('failed_posts');
        Schema::dropIfExists('post_logs');
        Schema::dropIfExists('post_media');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('social_pages');
        Schema::dropIfExists('social_accounts');
    }
};
