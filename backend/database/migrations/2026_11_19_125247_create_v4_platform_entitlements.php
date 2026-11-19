<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_claims', function (Blueprint $table): void {
            $table->string('method', 20)->default('phone_otp')->change();

        });

        if (! Schema::hasIndex('listing_claims', ['school_id'])) {
            Schema::table('listing_claims', fn (Blueprint $table) => $table->index('school_id', 'claims_school_scope_index'));
        }

        Schema::create('otp_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            $table->string('destination', 190);
            $table->string('purpose', 30);
            $table->string('code_hash');
            $table->tinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['destination', 'purpose']);
        });

        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('plan_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('limit_key', 50);
            $table->integer('limit_value')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'limit_key']);
        });

        Schema::create('plan_modules', function (Blueprint $table): void {
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['plan_id', 'module_id']);
        });

        Schema::create('school_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_group_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('grace_ends_at')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('limit_key', 50);
            $table->integer('limit_value')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->unique(['school_subscription_id', 'limit_key']);
        });

        Schema::create('subscription_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_subscription_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->restrictOnDelete();
            $table->string('source', 15)->default('addon');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('demo_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('institute_name', 150)->nullable();
            $table->string('institute_type', 30)->nullable();
            $table->string('phone', 30);
            $table->string('email', 190)->nullable();
            $table->string('city', 100)->nullable();
            $table->smallInteger('branches')->nullable();
            $table->integer('students_estimate')->nullable();
            $table->text('message')->nullable();
            $table->string('source_site', 30)->default('marketing');
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('school_group_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'demo_requests', 'subscription_modules', 'subscription_limits', 'school_subscriptions', 'plan_modules', 'plan_limits', 'plans', 'otp_codes'] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasIndex('listing_claims', 'claims_school_scope_index')) {
            Schema::table('listing_claims', fn (Blueprint $table) => $table->dropIndex('claims_school_scope_index'));
        }

        Schema::table('listing_claims', function (Blueprint $table): void {
            $table->string('method', 20)->default('manual')->change();
        });
    }
};
