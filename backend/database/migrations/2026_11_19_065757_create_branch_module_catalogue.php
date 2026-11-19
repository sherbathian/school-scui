<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->string('module_group', 30)->default('management');
            $table->json('applies_to')->nullable();
            $table->boolean('is_core')->default(false);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('school_modules', function (Blueprint $table): void {
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('enabled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('enabled_at')->nullable();
            $table->timestamps();
            $table->primary(['school_id', 'module_id']);
        });
        Schema::create('module_dependencies', function (Blueprint $table): void {
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requires_module_id')->constrained('modules')->cascadeOnDelete();
            $table->primary(['module_id', 'requires_module_id']);
        });

        $labels = [
            'listing' => 'Free directory listing',
            'academics' => 'Academic setup',
            'students' => 'Students',
            'inquiries' => 'Admission enquiries',
            'guardians' => 'Guardians',
            'attendance' => 'Attendance',
            'staff' => 'Staff',
            'fees' => 'Fees',
            'expenses' => 'Expenses',
        ];
        $order = 0;
        foreach ($labels as $slug => $name) {
            DB::table('modules')->insert([
                'slug' => $slug, 'name' => $name, 'is_core' => $slug === 'listing',
                'module_group' => $slug === 'listing' ? 'listing' : 'management',
                'sort_order' => ++$order, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('module_dependencies')->insert([
            'module_id' => DB::table('modules')->where('slug', 'fees')->value('id'),
            'requires_module_id' => DB::table('modules')->where('slug', 'students')->value('id'),
        ]);
    }

    public function down(): void
    {
        if (DB::table('school_modules')->exists()) {
            throw new RuntimeException('Export the branch module settings before rolling back.');
        }

        Schema::dropIfExists('module_dependencies');
        Schema::dropIfExists('school_modules');
        Schema::dropIfExists('modules');
    }
};
