<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $invalidCharge = DB::table('student_fee_charges as charges')
            ->join('fee_masters as masters', 'masters.id', '=', 'charges.fee_master_id')
            ->join('school_sessions as sessions', 'sessions.id', '=', 'charges.school_session_id')
            ->where(function ($query) {
                $query->whereColumn('charges.school_id', '!=', 'masters.school_id')
                    ->orWhereColumn('charges.school_id', '!=', 'sessions.school_id');
            })
            ->value('charges.id');

        if ($invalidCharge !== null) {
            throw new RuntimeException("Student fee charge {$invalidCharge} references another branch. Correct its references before migrating.");
        }

        Schema::table('fee_masters', function (Blueprint $table) {
            $table->unique(['school_id', 'id'], 'fee_masters_school_id_unique');
        });

        Schema::table('student_fee_charges', function (Blueprint $table) {
            $table->index(['school_id', 'fee_master_id'], 'student_fee_charges_school_master_index');
            $table->foreign(['school_id', 'fee_master_id'], 'student_fee_charges_school_master_fk')
                ->references(['school_id', 'id'])->on('fee_masters')->restrictOnDelete();
            $table->foreign(['school_id', 'school_session_id'], 'student_fee_charges_school_session_fk')
                ->references(['school_id', 'id'])->on('school_sessions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_fee_charges', function (Blueprint $table) {
            $table->dropForeign('student_fee_charges_school_master_fk');
            $table->dropForeign('student_fee_charges_school_session_fk');
        });

        Schema::table('student_fee_charges', function (Blueprint $table) {
            $table->dropIndex('student_fee_charges_school_master_index');
        });

        Schema::table('fee_masters', function (Blueprint $table) {
            $table->dropUnique('fee_masters_school_id_unique');
        });
    }
};
