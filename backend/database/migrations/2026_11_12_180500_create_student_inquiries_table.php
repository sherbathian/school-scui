<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('guardian_name', 180)->nullable();
            $table->string('guardian_phone', 50)->nullable();
            $table->string('guardian_email')->nullable();
            $table->foreignId('requested_grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->date('inquiry_date');
            $table->string('source', 80)->nullable();
            $table->string('status', 30)->default('new');
            $table->text('notes')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'status', 'inquiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_inquiries');
    }
};
