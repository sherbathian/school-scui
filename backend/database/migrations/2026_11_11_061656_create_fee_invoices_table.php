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
        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id');
            $table->foreignId('student_id');
            $table->foreignId('fee_type_id');
            $table->string('academic_year', 20);
            $table->string('description');
            $table->unsignedBigInteger('amount_cents');
            $table->date('due_date')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();

            $table->unique(['school_id', 'student_id', 'fee_type_id', 'academic_year'], 'fee_invoices_school_student_type_year_unique');
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'academic_year', 'status']);
            $table->foreign(['school_id', 'student_id'], 'fee_invoices_school_student_fk')
                ->references(['school_id', 'id'])
                ->on('students')
                ->cascadeOnDelete();
            $table->foreign(['school_id', 'fee_type_id'], 'fee_invoices_school_type_fk')
                ->references(['school_id', 'id'])
                ->on('fee_types')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_invoices');
    }
};
