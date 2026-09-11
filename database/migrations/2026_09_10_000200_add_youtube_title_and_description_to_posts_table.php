<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('youtube_title', 100)->nullable()->after('youtube_privacy');
            $table->text('youtube_description')->nullable()->after('youtube_title');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['youtube_title', 'youtube_description']);
        });
    }
};
