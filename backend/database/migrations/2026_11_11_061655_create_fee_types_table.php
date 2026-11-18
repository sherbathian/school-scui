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
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('academic_year', 20);
            $table->unsignedBigInteger('amount_cents');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'title', 'academic_year']);
            $table->unique(['school_id', 'id']);
            $table->index(['school_id', 'academic_year', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_types');
    }
};
