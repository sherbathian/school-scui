<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('family_fee_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable();
            $table->string('source_parent_id', 50)->nullable();
            $table->string('receipt_number', 100);
            $table->string('source_key', 100)->nullable();
            $table->date('receipt_date');
            $table->decimal('total_fee', 12, 2)->nullable();
            $table->decimal('discount', 12, 2)->nullable();
            $table->decimal('arrears', 12, 2)->nullable();
            $table->decimal('fund', 12, 2)->nullable();
            $table->decimal('manual_discount', 12, 2)->nullable();
            $table->decimal('total_payable', 12, 2)->nullable();
            $table->decimal('received', 12, 2)->nullable();
            $table->decimal('balance', 12, 2)->nullable();
            $table->string('source_sheet', 80)->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->json('source_data')->nullable();
            $table->timestamps();

            $table->foreign(['school_id', 'guardian_id'], 'family_fee_receipts_school_guardian_fk')
                ->references(['school_id', 'id'])->on('guardians')->restrictOnDelete();
            $table->unique(['school_id', 'receipt_number'], 'family_fee_receipts_school_number_unique');
            $table->unique(['school_id', 'source_key'], 'family_fee_receipts_school_source_key_unique');
            $table->index(['school_id', 'receipt_date'], 'family_fee_receipts_school_date_index');
            $table->index(['school_id', 'source_parent_id'], 'family_fee_receipts_school_parent_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_fee_receipts');
    }
};
