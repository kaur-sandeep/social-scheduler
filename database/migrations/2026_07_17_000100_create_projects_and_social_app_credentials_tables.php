<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->index(['user_id', 'name']);
        });

        Schema::create('social_app_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('client_id');
            $table->text('client_secret');
            $table->string('redirect_uri')->nullable();
            $table->json('additional_settings')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->unique(['project_id', 'provider']);
            $table->index(['project_id', 'provider', 'status']);
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->dropUnique('social_account_identity_unique');
            $table->unique(['project_id', 'provider', 'provider_user_id', 'user_id'], 'social_account_project_identity_unique');
            $table->index(['project_id', 'provider', 'status']);
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['project_id', 'status', 'scheduled_at']);
        });

        // Preserve existing installations by placing each user's existing records in one project.
        DB::table('users')->orderBy('id')->each(function ($user): void {
            $projectId = DB::table('projects')->insertGetId(['user_id' => $user->id, 'name' => 'Default Project', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('social_accounts')->where('user_id', $user->id)->update(['project_id' => $projectId]);
            DB::table('posts')->where('user_id', $user->id)->update(['project_id' => $projectId]);
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropConstrainedForeignId('project_id'));
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->dropUnique('social_account_project_identity_unique');
            $table->unique(['provider', 'provider_user_id', 'user_id'], 'social_account_identity_unique');
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::dropIfExists('social_app_credentials');
        Schema::dropIfExists('projects');
    }
};
