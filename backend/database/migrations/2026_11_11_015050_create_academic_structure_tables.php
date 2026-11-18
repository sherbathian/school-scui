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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'title']);
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'title']);
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('grade_section', function (Blueprint $table) {
            $table->foreignId('school_id');
            $table->foreignId('grade_id');
            $table->foreignId('section_id');
            $table->timestamps();

            $table->unique(['school_id', 'grade_id', 'section_id']);
            $table->index('grade_id');
            $table->index('section_id');
            $table->foreign(['school_id', 'grade_id'], 'grade_section_school_grade_fk')
                ->references(['school_id', 'id'])
                ->on('grades')
                ->cascadeOnDelete();
            $table->foreign(['school_id', 'section_id'], 'grade_section_school_section_fk')
                ->references(['school_id', 'id'])
                ->on('sections')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_section');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('grades');
    }
};
