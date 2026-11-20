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
        Schema::table('school_user', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->unique(['school_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_user', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'student_id']);
            $table->dropConstrainedForeignId('student_id');
        });
    }
};
