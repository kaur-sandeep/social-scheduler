<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_pages', function (Blueprint $table) {

            $table->string('instagram_business_id')->nullable()->after('page_access_token');

            $table->string('instagram_username')->nullable()->after('instagram_business_id');

            $table->text('instagram_profile_image')->nullable()->after('instagram_username');

        });
    }

    public function down(): void
    {
        Schema::table('social_pages', function (Blueprint $table) {

            $table->dropColumn([
                'instagram_business_id',
                'instagram_username',
                'instagram_profile_image'
            ]);

        });
    }
};