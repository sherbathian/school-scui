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
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id');
            $table->foreignId('fee_invoice_id');
            $table->unsignedBigInteger('amount_cents');
            $table->date('paid_on');
            $table->string('reference')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'paid_on']);
            $table->foreign(['school_id', 'fee_invoice_id'], 'fee_payments_school_invoice_fk')
                ->references(['school_id', 'id'])
                ->on('fee_invoices')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
