<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_receipt_reversals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('receipt_id');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason_code', 32);
            $table->date('reversed_on');
            $table->timestamp('recorded_at');
            $table->unique(['school_id', 'id'], 'receipt_reversals_branch_id_unique');
            $table->unique(['school_id', 'receipt_id'], 'receipt_reversals_original_unique');
            $table->foreign(['school_id', 'receipt_id'], 'receipt_reversals_original_fk')
                ->references(['school_id', 'id'])->on('posted_family_receipts')->restrictOnDelete();
        });
        Schema::create('family_receipt_reversal_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->char('operation_key', 64);
            $table->char('payload_hash', 64);
            $table->unsignedBigInteger('reversal_id')->nullable();
            $table->unique(['school_id', 'operation_key'], 'receipt_reversal_keys_branch_unique');
            $table->index(['school_id', 'reversal_id'], 'receipt_reversal_keys_result_idx');
            $table->foreign(['school_id', 'reversal_id'], 'receipt_reversal_keys_result_fk')
                ->references(['school_id', 'id'])->on('family_receipt_reversals')->restrictOnDelete();
        });
        Schema::table('family_receipt_finance_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('reversal_id')->nullable();
            $table->string('reason_code', 32)->nullable();
            $table->index(['school_id', 'reversal_id'], 'receipt_finance_events_reversal_idx');
            $table->foreign(['school_id', 'reversal_id'], 'receipt_finance_events_reversal_fk')
                ->references(['school_id', 'id'])->on('family_receipt_reversals')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $foreignKey = Schema::getConnection()->getDriverName() === 'sqlite'
            ? ['school_id', 'reversal_id'] : 'receipt_finance_events_reversal_fk';
        Schema::table('family_receipt_finance_events', function (Blueprint $table) use ($foreignKey): void {
            $table->dropForeign($foreignKey);
            $table->dropIndex('receipt_finance_events_reversal_idx');
            $table->dropColumn(['reversal_id', 'reason_code']);
        });
        Schema::dropIfExists('family_receipt_reversal_keys');
        Schema::dropIfExists('family_receipt_reversals');
    }
};
