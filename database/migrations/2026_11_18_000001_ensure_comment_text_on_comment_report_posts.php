<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comment_report_posts') && ! Schema::hasColumn('comment_report_posts', 'comment_text')) {
            Schema::table('comment_report_posts', function (Blueprint $table) {
                $table->text('comment_text')->after('position');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('comment_report_posts') && Schema::hasColumn('comment_report_posts', 'comment_text')) {
            Schema::table('comment_report_posts', function (Blueprint $table) {
                $table->dropColumn('comment_text');
            });
        }
    }
};
