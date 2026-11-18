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
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('father_name')->nullable();
            $table->string('father_phone')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('father_cnic', 30)->nullable()->index();
            $table->string('mother_name')->nullable();
            $table->string('mother_phone')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->string('mother_cnic', 30)->nullable()->index();
            $table->boolean('is_guardian_other')->default(false);
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relation')->nullable();
            $table->string('guardian_email')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('guardian_occupation')->nullable();
            $table->string('guardian_cnic', 30)->nullable()->index();
            $table->text('guardian_address')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'id']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('guardian_id')->nullable()->after('school_id');
            $table->foreign(['school_id', 'guardian_id'])->references(['school_id', 'id'])->on('guardians')->restrictOnDelete();
            $table->string('gender', 10)->nullable();
            $table->string('religion')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('b_form_number', 15)->nullable();
            $table->string('b_form_document_path')->nullable();
            $table->date('admission_date')->nullable();
            $table->date('leave_date')->nullable();
            $table->text('previous_school_details')->nullable();
            $table->text('note')->nullable();
            $table->softDeletes();
            $table->unique(['school_id', 'b_form_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'b_form_number']);
            $table->dropForeign(['school_id', 'guardian_id']);
            $table->dropColumn(['guardian_id', 'gender', 'religion', 'email', 'phone', 'address', 'photo_path', 'birth_date', 'b_form_number', 'b_form_document_path', 'admission_date', 'leave_date', 'previous_school_details', 'note', 'deleted_at']);
        });
        Schema::dropIfExists('guardians');
    }
};
