<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['designations', 'departments', 'leave_types'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained()->restrictOnDelete();
                $table->string('title', 100);
                $table->timestamps();
                $table->unique(['school_id', 'title']);
                $table->unique(['school_id', 'id']);
            });
        }

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('designation_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('staff_no', 30);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('father_name', 150)->nullable();
            $table->string('mother_name', 150)->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->date('birth_date');
            $table->date('joining_date');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('emergency_contact_number', 30)->nullable();
            $table->char('cnic', 13)->nullable();
            $table->string('photo_path')->nullable();
            $table->text('address')->nullable();
            $table->text('qualification')->nullable();
            $table->text('work_experience')->nullable();
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->string('bank_account_title', 150)->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('bank_branch_name', 150)->nullable();
            $table->text('bank_account_number')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'id']);
            $table->unique(['school_id', 'staff_no']);
            $table->unique(['school_id', 'cnic']);
            $table->unique(['school_id', 'email']);
            $table->foreign(['school_id', 'designation_id'])->references(['school_id', 'id'])->on('designations')->restrictOnDelete();
            $table->foreign(['school_id', 'department_id'])->references(['school_id', 'id'])->on('departments')->restrictOnDelete();
        });

        Schema::create('staff_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_session_id')->constrained()->restrictOnDelete();
            $table->decimal('allocated', 5, 1);
            $table->decimal('used', 5, 1)->default(0);
            $table->timestamps();
            $table->unique(['staff_id', 'leave_type_id', 'school_session_id'], 'staff_leave_balances_staff_type_session_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leave_balances');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('designations');
    }
};
