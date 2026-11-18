<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 20);
            $table->date('event_date');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_lifecycle_events');
    }
};
