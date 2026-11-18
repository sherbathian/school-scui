<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table): void {
            $table->string('father_doc_path')->nullable();
            $table->string('mother_doc_path')->nullable();
            $table->string('guardian_doc_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table): void {
            $table->dropColumn(['father_doc_path', 'mother_doc_path', 'guardian_doc_path']);
        });
    }
};
