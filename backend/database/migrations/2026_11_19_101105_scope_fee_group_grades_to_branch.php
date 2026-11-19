<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $invalidGroup = DB::table('fee_group_grade as assignments')
            ->join('fee_groups as groups', 'groups.id', '=', 'assignments.fee_group_id')
            ->join('grades', 'grades.id', '=', 'assignments.grade_id')
            ->whereColumn('groups.school_id', '!=', 'grades.school_id')
            ->value('assignments.fee_group_id');

        if ($invalidGroup !== null) {
            throw new RuntimeException("Fee group {$invalidGroup} references a grade from another branch. Correct the assignment before migrating.");
        }

        Schema::table('fee_group_grade', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable();
        });

        DB::table('fee_group_grade')->update([
            'school_id' => DB::raw('(select school_id from fee_groups where fee_groups.id = fee_group_grade.fee_group_id)'),
        ]);

        Schema::table('fee_group_grade', function (Blueprint $table) {
            $table->foreignId('school_id')->change();
            $table->index(['school_id', 'fee_group_id'], 'fee_group_grade_school_group_index');
            $table->index(['school_id', 'grade_id'], 'fee_group_grade_school_grade_index');
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign(['school_id', 'fee_group_id'], 'fee_group_grade_school_group_fk')
                ->references(['school_id', 'id'])->on('fee_groups')->cascadeOnDelete();
            $table->foreign(['school_id', 'grade_id'], 'fee_group_grade_school_grade_fk')
                ->references(['school_id', 'id'])->on('grades')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fee_group_grade', function (Blueprint $table) {
            $table->dropForeign('fee_group_grade_school_group_fk');
            $table->dropForeign('fee_group_grade_school_grade_fk');
            $table->dropForeign(['school_id']);
        });

        Schema::table('fee_group_grade', function (Blueprint $table) {
            $table->dropIndex('fee_group_grade_school_group_index');
            $table->dropIndex('fee_group_grade_school_grade_index');
            $table->dropColumn('school_id');
        });
    }
};
