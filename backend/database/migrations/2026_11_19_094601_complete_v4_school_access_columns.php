<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_user', function (Blueprint $table): void {
            $table->boolean('is_primary_contact')->default(false);
        });
        Schema::table('schools', function (Blueprint $table): void {
            $table->string('gender_type', 15)->nullable();
            $table->string('medium', 30)->nullable();
            $table->smallInteger('established_year')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->json('social_links')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->decimal('fee_min', 10, 2)->nullable()->index();
            $table->decimal('fee_max', 10, 2)->nullable();
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->dateTime('featured_until')->nullable();
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->string('branch_code', 20)->nullable();
            $table->unique(['group_id', 'branch_code']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable()->index();
            $table->dateTime('phone_verified_at')->nullable();
            $table->string('locale', 5)->default('en');
            $table->dateTime('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['phone']);
            $table->dropColumn(['phone', 'phone_verified_at', 'locale', 'last_login_at']);
        });
        // MySQL can use the new unique index to satisfy the existing group FK.
        if (! Schema::hasIndex('schools', ['group_id'])) {
            Schema::table('schools', function (Blueprint $table): void {
                $table->index('group_id');
            });
        }

        Schema::table('schools', function (Blueprint $table): void {
            $table->dropUnique(['group_id', 'branch_code']);
            $table->dropIndex(['fee_min']);
            $table->dropColumn([
                'gender_type', 'medium', 'established_year', 'latitude', 'longitude', 'whatsapp', 'social_links',
                'logo_path', 'cover_path', 'fee_min', 'fee_max', 'rating_avg', 'rating_count', 'is_featured',
                'featured_until', 'seo_title', 'seo_description', 'branch_code',
            ]);
        });
        Schema::table('school_user', function (Blueprint $table): void {
            $table->dropColumn('is_primary_contact');
        });
    }
};
