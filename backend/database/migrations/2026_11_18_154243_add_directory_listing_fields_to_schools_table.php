<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->string('institute_type', 30)->default('school')->index();
            $table->string('short_name', 80)->nullable();
            $table->string('branch_name', 100)->nullable();
            $table->string('tagline', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('address_line')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('website_url')->nullable();
            $table->string('listing_status', 20)->default('draft')->index();
            $table->string('source', 20)->default('admin_created');
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropIndex(['institute_type']);
            $table->dropIndex(['listing_status']);
            $table->dropColumn([
                'institute_type', 'short_name', 'branch_name', 'tagline', 'description',
                'address_line', 'phone', 'email', 'website_url', 'listing_status',
                'source', 'verified_at', 'published_at',
            ]);
        });
    }
};
