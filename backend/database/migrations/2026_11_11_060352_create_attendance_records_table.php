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
        Schema::table('enrollments', function (Blueprint $table) {
            $table->unique(['school_id', 'id']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id');
            $table->date('attendance_date');
            $table->string('status');
            $table->foreignId('marked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'enrollment_id', 'attendance_date'], 'attendance_school_enrollment_date_unique');
            $table->index(['school_id', 'attendance_date']);
            $table->foreign(['school_id', 'enrollment_id'], 'attendance_school_enrollment_fk')
                ->references(['school_id', 'id'])
                ->on('enrollments')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'id']);
        });
    }
};
