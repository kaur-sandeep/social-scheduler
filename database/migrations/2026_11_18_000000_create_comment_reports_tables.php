<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('project_name');
            $table->string('account_type', 32);
            $table->date('report_date');
            $table->unsignedSmallInteger('number_of_comments');
            $table->timestamps();
            $table->index(['user_id', 'report_date']);
        });

        Schema::create('comment_report_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_report_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('comment_text');
            $table->text('post_url');
            $table->timestamps();
            $table->unique(['comment_report_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_report_posts');
        Schema::dropIfExists('comment_reports');
    }
};