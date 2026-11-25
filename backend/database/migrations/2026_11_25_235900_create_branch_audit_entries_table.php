<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_audit_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->unsignedBigInteger('school_id');
            $table->string('action', 40);
            $table->string('target_id', 80);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('recorded_at');
            $table->index(['school_id', 'recorded_at', 'id']);
            $table->index(['recorded_at', 'id']);
        });

        DB::table('permissions')->insert(['name' => 'audit.view', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'audit.view')->where('guard_name', 'web')->delete();
        Schema::dropIfExists('branch_audit_entries');
    }
};
