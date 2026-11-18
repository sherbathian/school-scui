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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('admission_number');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'admission_number']);
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id');
            $table->foreignId('student_id');
            $table->foreignId('grade_id');
            $table->foreignId('section_id');
            $table->string('academic_year', 20);
            $table->string('status')->default('active');
            $table->date('enrolled_on');
            $table->date('ended_on')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['student_id', 'created_at']);
            $table->foreign(['school_id', 'student_id'], 'enrollments_school_student_fk')
                ->references(['school_id', 'id'])
                ->on('students')
                ->cascadeOnDelete();
            $table->foreign(['school_id', 'grade_id', 'section_id'], 'enrollments_school_grade_section_fk')
                ->references(['school_id', 'grade_id', 'section_id'])
                ->on('grade_section')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('students');
    }
};
