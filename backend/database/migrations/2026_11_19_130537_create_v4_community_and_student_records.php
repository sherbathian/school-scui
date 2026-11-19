<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->string('event_type', 30)->default('other');
            $table->text('description')->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('venue', 200)->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('registration_url', 500)->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('student_inquiries', function (Blueprint $table): void {
            $table->unique(['school_id', 'id'], 'inquiries_school_id_unique');
        });
        Schema::create('listing_leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('parent_name', 150);
            $table->string('phone', 30);
            $table->string('email', 190)->nullable();
            $table->string('grade_interested', 60)->nullable();
            $table->text('message')->nullable();
            $table->string('source', 30)->default('profile_page');
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('student_inquiry_id')->nullable();
            $table->boolean('consent_given')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'id'], 'listing_leads_school_id_unique');
            $table->unique(['school_id', 'student_inquiry_id'], 'listing_leads_school_inquiry_unique');
            $table->foreign(['school_id', 'student_inquiry_id'], 'listing_leads_school_inquiry_fk')
                ->references(['school_id', 'id'])->on('student_inquiries')->restrictOnDelete();
        });
        Schema::table('student_inquiries', function (Blueprint $table): void {
            $table->foreignId('listing_lead_id')->nullable();
            $table->unique(['school_id', 'listing_lead_id'], 'inquiries_school_lead_unique');
            $table->foreign(['school_id', 'listing_lead_id'])
                ->references(['school_id', 'id'])->on('listing_leads')->restrictOnDelete();
        });

        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('rating');
            $table->string('title', 150)->nullable();
            $table->text('body');
            $table->string('relationship', 30)->default('parent');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('moderated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('moderated_at')->nullable();
            $table->text('school_reply')->nullable();
            $table->foreignId('replied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('school_replied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('saved_schools', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['user_id', 'school_id']);
        });

        Schema::create('school_stats_daily', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->date('stat_date');
            $table->integer('profile_views')->default(0);
            $table->integer('saves')->default(0);
            $table->integer('compare_adds')->default(0);
            $table->integer('leads')->default(0);
            $table->timestamps();
            $table->unique(['school_id', 'stat_date']);
        });

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('excerpt', 300)->nullable();
            $table->longText('body');
            $table->string('cover_path')->nullable();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->dateTime('published_at')->nullable();
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->string('site', 20)->default('finder')->index();
            $table->timestamps();
        });

        Schema::create('student_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('to_school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('from_student_id');
            $table->foreignId('to_student_id');
            $table->date('transfer_date');
            $table->string('reason')->nullable();
            $table->foreignId('transferred_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            foreach (['from', 'to'] as $side) {
                $columns = [$side.'_school_id', $side.'_student_id'];
                $table->index($columns, 'transfers_'.$side.'_branch_idx');
                $table->foreign($columns, 'transfers_'.$side.'_branch_fk')
                    ->references(['school_id', 'id'])->on('students')->restrictOnDelete();
            }
        });

        Schema::create('student_fee_discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('student_id');
            $table->foreignId('fee_discount_id');
            $table->foreignId('school_session_id')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            foreach (['student' => 'students', 'fee_discount' => 'fee_discounts', 'school_session' => 'school_sessions'] as $key => $parent) {
                $columns = ['school_id', $key.'_id'];
                $table->index($columns, 'student_discounts_'.$key.'_idx');
                $table->foreign($columns, 'student_discounts_'.$key.'_fk')
                    ->references(['school_id', 'id'])->on($parent)->onDelete($key === 'school_session' ? 'restrict' : 'cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_inquiries', function (Blueprint $table): void {
            $table->dropForeign(['school_id', 'listing_lead_id']);
            $table->dropUnique('inquiries_school_lead_unique');
            $table->dropColumn('listing_lead_id');
        });
        foreach (['student_fee_discounts', 'student_transfers', 'articles', 'school_stats_daily', 'saved_schools', 'reviews', 'listing_leads', 'events'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('student_inquiries', function (Blueprint $table): void {
            $table->dropUnique('inquiries_school_id_unique');
        });
    }
};
