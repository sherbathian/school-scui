<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->text('description')->nullable();
            $table->string('website_url')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
        Schema::create('school_group_user', function (Blueprint $table): void {
            $table->foreignId('school_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30)->default('owner');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->primary(['school_group_id', 'user_id']);
        });
        Schema::table('schools', function (Blueprint $table): void {
            $table->foreignId('group_id')->nullable()->constrained('school_groups');
            $table->boolean('is_main_branch')->default(false);
            $table->string('claim_status', 20)->default('unclaimed')->index();
        });
        Schema::create('listing_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('claimant_name', 150);
            $table->string('claimant_role', 100)->nullable();
            $table->string('phone', 30);
            $table->string('email', 190)->nullable();
            $table->string('method', 20)->default('manual');
            $table->string('document_path')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();
        });

        DB::table('schools')->whereNotNull('verified_at')->whereNull('deleted_at')->chunkById(200, function ($schools): void {
            foreach ($schools as $school) {
                DB::transaction(function () use ($school): void {
                    $group = DB::table('school_groups')->insertGetId([
                        'name' => Str::limit($school->name, 150, ''),
                        'slug' => 'campus-'.$school->id.'-'.Str::limit($school->slug, 120, ''),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('schools')->where('id', $school->id)->update(['group_id' => $group, 'is_main_branch' => true]);
                });
            }
        });
    }

    public function down(): void
    {
        if (DB::table('listing_claims')->exists() || DB::table('school_group_user')->exists()) {
            throw new RuntimeException('Back up claim and organisation ownership records before rolling back.');
        }

        Schema::dropIfExists('listing_claims');
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('group_id');
            $table->dropIndex(['claim_status']);
            $table->dropColumn(['claim_status', 'is_main_branch']);
        });
        Schema::dropIfExists('school_group_user');
        Schema::dropIfExists('school_groups');
    }
};
