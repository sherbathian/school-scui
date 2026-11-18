<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'title']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_head_id')->constrained('expense_heads')->restrictOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->date('expense_date');
            $table->decimal('amount', 12, 2);
            $table->string('attachment_path')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'expense_date']);
            $table->unique(['school_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_heads');
    }
};
