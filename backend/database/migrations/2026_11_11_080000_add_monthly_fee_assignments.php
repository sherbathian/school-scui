<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_masters', function (Blueprint $table) {
            $table->string('billing_month', 7)->default('1970-01')->after('fee_type_id');
            $table->dropUnique('fee_masters_group_type_session_unique');
        });
        DB::table('fee_masters')->update(['billing_month' => DB::raw('substr(due_date, 1, 7)')]);
        Schema::table('fee_masters', function (Blueprint $table) {
            $table->unique(['fee_group_id', 'fee_type_id', 'school_session_id', 'billing_month'], 'fee_masters_monthly_unique');
        });

        Schema::create('student_fee_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_master_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id');
            $table->string('billing_month', 7);
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->string('status')->default('open');
            $table->timestamps();
            $table->unique(['fee_master_id', 'student_id', 'billing_month'], 'student_fee_charge_once_per_month');
            $table->foreign(['school_id', 'student_id'], 'student_fee_charges_school_student_fk')
                ->references(['school_id', 'id'])->on('students')->cascadeOnDelete();
            $table->index(['school_id', 'school_session_id', 'billing_month', 'status'], 'student_fee_charges_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fee_charges');
        Schema::table('fee_masters', function (Blueprint $table) {
            $table->dropUnique('fee_masters_monthly_unique');
            $table->dropColumn('billing_month');
            $table->unique(['fee_group_id', 'fee_type_id', 'school_session_id'], 'fee_masters_group_type_session_unique');
        });
    }
};
