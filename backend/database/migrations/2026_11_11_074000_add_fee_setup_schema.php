<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('fee_types', 'legacy_fee_types');
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id');
            $table->foreign('school_id', 'fee_types_catalog_school_fk')
                ->references('id')
                ->on('schools')
                ->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'title'], 'fee_types_school_title_unique');
            $table->unique(['school_id', 'id'], 'fee_types_school_catalog_id_unique');
        });

        Schema::table('school_sessions', function (Blueprint $table) {
            $table->unique(['school_id', 'id'], 'school_sessions_school_id_unique');
        });

        Schema::create('fee_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('value', 10, 2);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'title']);
            $table->unique(['school_id', 'id']);
        });

        Schema::create('fee_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->boolean('allows_discount')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'title']);
            $table->unique(['school_id', 'id']);
        });

        Schema::create('fee_group_grade', function (Blueprint $table) {
            $table->foreignId('fee_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained()->restrictOnDelete();
            $table->primary(['fee_group_id', 'grade_id']);
            $table->index('grade_id');
        });

        Schema::create('fee_masters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_session_id');
            $table->foreignId('fee_group_id');
            $table->foreignId('fee_type_id');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->enum('fine_type', ['none', 'fixed', 'percentage'])->default('none');
            $table->decimal('fine_value', 10, 2)->default(0);
            $table->timestamps();
            $table->unique(['fee_group_id', 'fee_type_id', 'school_session_id'], 'fee_masters_group_type_session_unique');
            $table->foreign(['school_id', 'school_session_id'], 'fee_masters_school_session_fk')
                ->references(['school_id', 'id'])->on('school_sessions')->restrictOnDelete();
            $table->foreign(['school_id', 'fee_group_id'], 'fee_masters_school_group_fk')
                ->references(['school_id', 'id'])->on('fee_groups')->restrictOnDelete();
            $table->foreign(['school_id', 'fee_type_id'], 'fee_masters_school_type_fk')
                ->references(['school_id', 'id'])->on('fee_types')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE fee_discounts ADD CONSTRAINT fee_discounts_value_nonnegative CHECK (value >= 0)');
            DB::statement("ALTER TABLE fee_discounts ADD CONSTRAINT fee_discounts_percentage_max CHECK (discount_type <> 'percentage' OR value <= 100)");
            DB::statement('ALTER TABLE fee_masters ADD CONSTRAINT fee_masters_amount_nonnegative CHECK (amount >= 0)');
            DB::statement('ALTER TABLE fee_masters ADD CONSTRAINT fee_masters_fine_nonnegative CHECK (fine_value >= 0)');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement("CREATE TRIGGER fee_discounts_validate_insert BEFORE INSERT ON fee_discounts WHEN NEW.value < 0 OR (NEW.discount_type = 'percentage' AND NEW.value > 100) BEGIN SELECT RAISE(ABORT, 'Invalid fee discount value'); END");
            DB::statement("CREATE TRIGGER fee_discounts_validate_update BEFORE UPDATE ON fee_discounts WHEN NEW.value < 0 OR (NEW.discount_type = 'percentage' AND NEW.value > 100) BEGIN SELECT RAISE(ABORT, 'Invalid fee discount value'); END");
            DB::statement('CREATE TRIGGER fee_masters_validate_insert BEFORE INSERT ON fee_masters WHEN NEW.amount < 0 OR NEW.fine_value < 0 BEGIN SELECT RAISE(ABORT, \'Invalid fee master amount\'); END');
            DB::statement('CREATE TRIGGER fee_masters_validate_update BEFORE UPDATE ON fee_masters WHEN NEW.amount < 0 OR NEW.fine_value < 0 BEGIN SELECT RAISE(ABORT, \'Invalid fee master amount\'); END');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_masters');
        Schema::dropIfExists('fee_group_grade');
        Schema::dropIfExists('fee_groups');
        Schema::dropIfExists('fee_discounts');
        Schema::table('school_sessions', function (Blueprint $table) {
            $table->dropUnique('school_sessions_school_id_unique');
        });
        Schema::dropIfExists('fee_types');
        Schema::rename('legacy_fee_types', 'fee_types');
    }
};
