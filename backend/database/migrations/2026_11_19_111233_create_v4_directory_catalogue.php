<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->locationAndGroupRules(true);

        foreach (['boards', 'education_levels', 'facilities', 'course_categories'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                if ($name === 'boards') {
                    $table->string('kind', 30)->default('board');
                }
                if ($name === 'facilities') {
                    $table->string('kind', 20)->default('facility');
                    $table->string('icon', 60)->nullable();
                }
                if (in_array($name, ['boards', 'facilities'], true)) {
                    $table->boolean('is_active')->default(true);
                }
                $table->smallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        foreach (['board' => 'boards', 'education_level' => 'education_levels', 'facility' => 'facilities'] as $key => $parent) {
            Schema::create('school_'.$key, function (Blueprint $table) use ($key, $parent): void {
                $table->foreignId('school_id')->constrained()->cascadeOnDelete();
                $table->foreignId($key.'_id')->constrained($parent)->cascadeOnDelete();
                $table->primary(['school_id', $key.'_id']);
            });
        }

        Schema::create('school_listing_fees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 100)->nullable();
            $table->decimal('monthly_fee', 10, 2)->nullable();
            $table->decimal('admission_fee', 10, 2)->nullable();
            $table->decimal('annual_charges', 10, 2)->nullable();
            $table->char('currency', 3)->default('PKR');
            $table->date('effective_from')->nullable();
            $table->dateTime('last_confirmed_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('school_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->index()->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('photo');
            $table->string('path')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('caption')->nullable();
            $table->string('alt_text')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_approved')->default(true);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_name', 150)->nullable();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->string('slug', 170)->unique();
            $table->string('summary', 300)->nullable();
            $table->text('description')->nullable();
            $table->smallInteger('duration_value')->nullable();
            $table->string('duration_unit', 10)->nullable();
            $table->string('mode', 15)->default('onsite');
            $table->decimal('fee', 10, 2)->nullable();
            $table->date('next_start_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });

        Schema::create('programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->index()->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('slug', 150);
            $table->string('level', 30)->default('bachelor');
            $table->string('field', 100)->nullable();
            $table->smallInteger('duration_months')->nullable();
            $table->decimal('fee_amount', 10, 2)->nullable();
            $table->string('fee_period', 20)->nullable();
            $table->text('eligibility')->nullable();
            $table->boolean('admission_open')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['school_id', 'slug']);
        });
    }

    public function down(): void
    {
        foreach (['programs', 'courses', 'school_media', 'school_listing_fees', 'school_facility', 'school_education_level', 'school_board', 'course_categories', 'facilities', 'education_levels', 'boards'] as $table) {
            Schema::dropIfExists($table);
        }

        $this->locationAndGroupRules(false);
    }

    private function locationAndGroupRules(bool $useV4): void
    {
        Schema::table('areas', function (Blueprint $table): void {
            $table->dropForeign(['city_id']);
        });
        Schema::table('areas', function (Blueprint $table) use ($useV4): void {
            $table->foreign('city_id')->references('id')->on('cities')->onDelete($useV4 ? 'cascade' : 'no action');
        });
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['area_id']);
            $table->dropForeign(['group_id']);
        });
        Schema::table('schools', function (Blueprint $table) use ($useV4): void {
            $table->foreign('city_id')->references('id')->on('cities')->onDelete($useV4 ? 'restrict' : 'no action');
            $table->foreign('area_id')->references('id')->on('areas')->onDelete($useV4 ? 'set null' : 'no action');
            $table->foreign('group_id')->references('id')->on('school_groups')->onDelete($useV4 ? 'set null' : 'no action');
        });
    }
};
