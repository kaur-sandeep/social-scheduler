<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->json('metrics_snapshot')->nullable()->after('provider_post_id');
            $table->timestamp('metrics_updated_at')->nullable()->after('metrics_snapshot')->index();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropIndex(['metrics_updated_at']);
            $table->dropColumn(['metrics_snapshot', 'metrics_updated_at']);
        });
    }
};
