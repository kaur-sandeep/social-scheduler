<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->string('authentication_method', 32)->nullable()->after('provider');
        });

        Schema::table('social_pages', function (Blueprint $table): void {
            $table->timestamp('last_synced_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('social_pages', fn (Blueprint $table) => $table->dropColumn('last_synced_at'));
        Schema::table('social_accounts', fn (Blueprint $table) => $table->dropColumn('authentication_method'));
    }
};
