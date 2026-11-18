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
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'title']);
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('grade_subject', function (Blueprint $table) {
            $table->foreignId('school_id');
            $table->foreignId('grade_id');
            $table->foreignId('subject_id');
            $table->timestamps();

            $table->unique(['school_id', 'grade_id', 'subject_id']);
            $table->index('subject_id');
            $table->foreign(['school_id', 'grade_id'], 'grade_subject_school_grade_fk')
                ->references(['school_id', 'id'])
                ->on('grades')
                ->cascadeOnDelete();
            $table->foreign(['school_id', 'subject_id'], 'grade_subject_school_subject_fk')
                ->references(['school_id', 'id'])
                ->on('subjects')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_subject');
        Schema::dropIfExists('subjects');
    }
};
