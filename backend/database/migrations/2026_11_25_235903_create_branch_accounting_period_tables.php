<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_accounting_settings', function (Blueprint $table): void {
            $table->foreignId('school_id')->primary()->constrained('schools')->restrictOnDelete();
            $table->string('timezone', 64);
        });

        Schema::create('branch_accounting_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('month', 7);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->enum('open_marker', ['open'])->nullable();
            $table->unique(['school_id', 'id'], 'accounting_period_branch_id_unique');
            $table->unique(['school_id', 'month'], 'accounting_period_branch_month_unique');
            $table->unique(['school_id', 'open_marker'], 'accounting_period_branch_open_unique');
        });

        Schema::create('branch_accounting_period_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('period_id');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 24);
            $table->string('from_state', 8)->nullable();
            $table->string('to_state', 8);
            $table->string('month', 7);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('timezone', 64);
            $table->date('business_date');
            $table->dateTime('recorded_at_utc');
            $table->unique(['school_id', 'period_id', 'action'], 'accounting_period_event_action_unique');
            $table->foreign(['school_id', 'period_id'], 'accounting_period_event_period_fk')
                ->references(['school_id', 'id'])->on('branch_accounting_periods')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_accounting_period_events');
        Schema::dropIfExists('branch_accounting_periods');
        Schema::dropIfExists('branch_accounting_settings');
    }
};
