<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_fee_receipts', function (Blueprint $table): void {
            $table->unique(['school_id', 'id'], 'family_fee_receipts_branch_id_unique');
        });
        Schema::table('student_fee_charges', function (Blueprint $table): void {
            $table->unique(['school_id', 'id'], 'student_fee_charges_branch_id_unique');
        });
        Schema::create('posted_family_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('source_receipt_id')->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->date('received_on');
            $table->timestamp('posted_at');
            $table->unique(['school_id', 'id'], 'posted_receipts_branch_id_unique');
            $table->unique(['school_id', 'source_receipt_id'], 'posted_receipts_source_unique');
            $table->foreign(['school_id', 'source_receipt_id'], 'posted_receipts_source_fk')
                ->references(['school_id', 'id'])->on('family_fee_receipts')->restrictOnDelete();
        });
        Schema::create('family_receipt_posting_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->char('operation_key', 64);
            $table->char('payload_hash', 64);
            $table->unsignedBigInteger('receipt_id')->nullable();
            $table->unique(['school_id', 'operation_key'], 'receipt_posting_keys_branch_unique');
            $table->index(['school_id', 'receipt_id'], 'receipt_posting_keys_receipt_idx');
            $table->foreign(['school_id', 'receipt_id'], 'receipt_posting_keys_receipt_fk')
                ->references(['school_id', 'id'])->on('posted_family_receipts')->restrictOnDelete();
        });
        Schema::create('posted_family_receipt_allocations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('receipt_id');
            $table->unsignedBigInteger('charge_id');
            $table->unsignedBigInteger('amount_minor');
            $table->unique(['school_id', 'receipt_id', 'charge_id'], 'posted_allocations_receipt_charge_unique');
            $table->index(['school_id', 'charge_id'], 'posted_allocations_branch_charge_idx');
            $table->foreign(['school_id', 'receipt_id'], 'posted_allocations_receipt_fk')
                ->references(['school_id', 'id'])->on('posted_family_receipts')->restrictOnDelete();
            $table->foreign(['school_id', 'charge_id'], 'posted_allocations_charge_fk')
                ->references(['school_id', 'id'])->on('student_fee_charges')->restrictOnDelete();
        });
        Schema::create('family_receipt_finance_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('receipt_id');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 32);
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->timestamp('recorded_at');
            $table->json('balances');
            $table->json('allocation_ids');
            $table->unique(['school_id', 'receipt_id', 'action'], 'receipt_finance_events_action_unique');
            $table->foreign(['school_id', 'receipt_id'], 'receipt_finance_events_receipt_fk')
                ->references(['school_id', 'id'])->on('posted_family_receipts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_receipt_finance_events');
        Schema::dropIfExists('posted_family_receipt_allocations');
        Schema::dropIfExists('family_receipt_posting_keys');
        Schema::dropIfExists('posted_family_receipts');
        Schema::table('student_fee_charges', fn (Blueprint $table) => $table->dropUnique('student_fee_charges_branch_id_unique'));
        Schema::table('family_fee_receipts', fn (Blueprint $table) => $table->dropUnique('family_fee_receipts_branch_id_unique'));
    }
};
