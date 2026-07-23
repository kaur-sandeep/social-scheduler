<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('post_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('post_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('project')->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('account')->nullable();
            $table->text('error_message');
            $table->timestamps();
            $table->index(['post_import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_import_errors');
        Schema::dropIfExists('post_imports');
    }
};
