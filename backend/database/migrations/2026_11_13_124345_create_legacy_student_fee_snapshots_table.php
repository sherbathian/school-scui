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
        Schema::create('legacy_student_fee_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id');
            $table->string('billing_month', 7);
            $table->decimal('balance_amount', 12, 2)->nullable();
            $table->decimal('received_amount', 12, 2)->nullable();
            $table->string('balance_source_label', 80)->nullable();
            $table->string('received_source_label', 80)->nullable();
            $table->string('source_sheet', 80)->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->json('source_data')->nullable();
            $table->timestamps();

            $table->foreign(['school_id', 'student_id'], 'legacy_fee_snapshots_school_student_fk')
                ->references(['school_id', 'id'])->on('students')->cascadeOnDelete();
            $table->unique(['school_id', 'student_id', 'billing_month'], 'legacy_fee_snapshots_student_month_unique');
            $table->index(['school_id', 'billing_month'], 'legacy_fee_snapshots_school_month_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_student_fee_snapshots');
    }
};
