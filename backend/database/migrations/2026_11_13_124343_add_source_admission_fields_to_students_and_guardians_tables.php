<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table): void {
            $table->string('source_parent_id', 50)->nullable()->after('school_id');
            $table->json('source_data')->nullable();
            $table->unique(['school_id', 'source_parent_id'], 'guardians_school_source_parent_unique');
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->string('full_name', 180)->nullable()->after('last_name');
            $table->string('admission_type', 40)->nullable()->after('admission_number');
            $table->string('previous_admission_number', 50)->nullable()->after('admission_type');
            $table->string('admitted_class', 80)->nullable()->after('previous_admission_number');
            $table->string('current_class_label', 80)->nullable()->after('admitted_class');
            $table->string('caste_or_tribe', 100)->nullable();
            $table->decimal('fee_rate', 12, 2)->nullable();
            $table->decimal('fee_amount', 12, 2)->nullable();
            $table->string('fee_waiver', 80)->nullable();
            $table->decimal('arrears_due', 12, 2)->nullable();
            $table->string('withdrawn_from_class', 80)->nullable();
            $table->decimal('source_marks_obtained', 10, 2)->nullable();
            $table->decimal('source_marks_percentage', 5, 2)->nullable();
            $table->json('source_data')->nullable();
            $table->json('source_guardian_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn([
                'full_name', 'admission_type', 'previous_admission_number', 'admitted_class', 'current_class_label',
                'caste_or_tribe', 'fee_rate', 'fee_amount', 'fee_waiver', 'arrears_due', 'withdrawn_from_class',
                'source_marks_obtained', 'source_marks_percentage', 'source_data', 'source_guardian_data',
            ]);
        });

        Schema::table('guardians', function (Blueprint $table): void {
            $table->dropUnique('guardians_school_source_parent_unique');
            $table->dropColumn(['source_parent_id', 'source_data']);
        });
    }
};
