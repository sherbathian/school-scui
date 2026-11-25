<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array TABLES = ['posted_family_receipts', 'family_receipt_reversals', 'family_receipt_finance_events'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->unsignedBigInteger('period_id')->nullable();
                $table->date('accounting_date')->nullable();
                $table->string('accounting_timezone', 64)->nullable();
                $table->dateTime('accounting_recorded_at_utc')->nullable();
                $table->index(['school_id', 'period_id']);
                $table->foreign(['school_id', 'period_id'])
                    ->references(['school_id', 'id'])->on('branch_accounting_periods')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropForeign(['school_id', 'period_id']);
                $table->dropIndex(['school_id', 'period_id']);
                $table->dropColumn(['period_id', 'accounting_date', 'accounting_timezone', 'accounting_recorded_at_utc']);
            });
        }
    }
};
