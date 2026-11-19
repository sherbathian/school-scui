<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_group_id')->index()->constrained()->restrictOnDelete();
            $table->string('invoice_no', 30)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('unpaid')->index();
            $table->date('due_date');
            $table->dateTime('issued_at')->nullable();
            $table->timestamps();
        });
        Schema::create('subscription_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_invoice_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 200);
            $table->smallInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_invoice_id')->index()->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->string('method', 20)->default('bank_transfer');
            $table->string('reference', 100)->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('site_themes', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('description')->nullable();
            $table->string('preview_path')->nullable();
            $table->string('version', 20)->default('1.0.0');
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('settings_schema')->nullable();
            $table->timestamps();
        });
        Schema::create('school_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->index()->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('slug', 150);
            $table->string('page_type', 20)->default('custom');
            $table->longText('content')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('show_in_menu')->default(false);
            $table->smallInteger('sort_order')->default(0);
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'slug']);
        });
        Schema::create('school_site_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('site_theme_id')->constrained()->restrictOnDelete();
            $table->string('subdomain', 63)->nullable()->unique();
            $table->boolean('site_enabled')->default(false);
            $table->string('primary_color', 7)->nullable();
            $table->string('secondary_color', 7)->nullable();
            $table->string('font_family', 60)->nullable();
            $table->json('homepage_sections')->nullable();
            $table->json('menu_items')->nullable();
            $table->string('footer_text')->nullable();
            $table->string('analytics_id', 40)->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
        });
        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('platform', 10);
            $table->string('app_version', 20)->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['personal_access_tokens', 'device_tokens', 'school_site_settings', 'school_pages', 'site_themes', 'subscription_payments', 'subscription_invoice_lines', 'subscription_invoices'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
