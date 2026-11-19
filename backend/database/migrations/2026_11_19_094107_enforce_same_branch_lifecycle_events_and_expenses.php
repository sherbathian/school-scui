<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertMatchingBranches('student_lifecycle_events', 'student_id', 'students');
        $this->assertMatchingBranches('expenses', 'expense_head_id', 'expense_heads');

        if (! Schema::hasIndex('expense_heads', ['school_id', 'id'], 'unique')) {
            Schema::table('expense_heads', function (Blueprint $table): void {
                $table->unique(['school_id', 'id']);
            });
        }

        $this->replaceLink('student_lifecycle_events', 'student_id', 'students', 'cascade', scoped: true);
        $this->replaceLink('expenses', 'expense_head_id', 'expense_heads', 'restrict', scoped: true);
    }

    public function down(): void
    {
        $this->replaceLink('expenses', 'expense_head_id', 'expense_heads', 'restrict', scoped: false);
        $this->replaceLink('student_lifecycle_events', 'student_id', 'students', 'cascade', scoped: false);

        if (Schema::hasIndex('expense_heads', 'expense_heads_school_id_id_unique')) {
            Schema::table('expense_heads', function (Blueprint $table): void {
                $table->dropUnique(['school_id', 'id']);
            });
        }
    }

    private function assertMatchingBranches(string $child, string $column, string $parent): void
    {
        $invalidIds = DB::table($child)
            ->leftJoin($parent, "{$child}.{$column}", '=', "{$parent}.id")
            ->where(fn ($query) => $query->whereNull("{$parent}.id")
                ->orWhereColumn("{$child}.school_id", '!=', "{$parent}.school_id"))
            ->orderBy("{$child}.id")
            ->limit(10)
            ->pluck("{$child}.id");

        if ($invalidIds->isNotEmpty()) {
            throw new RuntimeException("Invalid branch references in {$child}: ".$invalidIds->implode(', ').'. Reconcile these records before migrating.');
        }
    }

    private function replaceLink(string $child, string $column, string $parent, string $onDelete, bool $scoped): void
    {
        $pair = ['school_id', $column];
        $next = $scoped ? $pair : [$column];
        $previous = $scoped ? [$column] : $pair;
        $addIndex = $scoped && ! Schema::hasIndex($child, $pair);
        $dropIndex = ! $scoped && Schema::hasIndex($child, "{$child}_school_id_{$column}_index");
        $addForeign = ! Schema::hasForeignKey($child, $next);
        $dropForeign = Schema::hasForeignKey($child, $previous);

        // MySQL can replace its implicit school FK index with the composite index.
        $restoreSchoolIndex = $dropIndex && ! collect(Schema::getIndexes($child))->contains(
            fn (array $index) => $index['columns'][0] === 'school_id' && $index['columns'] !== $pair
        );

        Schema::table($child, function (Blueprint $table) use (
            $pair, $next, $previous, $parent, $onDelete, $scoped, $addIndex, $dropIndex, $addForeign, $dropForeign, $restoreSchoolIndex
        ): void {
            if ($addIndex) {
                $table->index($pair);
            }
            if ($addForeign) {
                $table->foreign($next)->references($scoped ? ['school_id', 'id'] : ['id'])->on($parent)->onDelete($onDelete);
            }
            if ($dropForeign) {
                $table->dropForeign($previous);
            }
            if ($restoreSchoolIndex) {
                $table->index('school_id');
            }
            if ($dropIndex) {
                $table->dropIndex($pair);
            }
        });
    }
};
