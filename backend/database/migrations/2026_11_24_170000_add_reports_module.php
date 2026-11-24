<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modules')->insert([
            'slug' => 'reports', 'name' => 'Reports', 'module_group' => 'management',
            'description' => 'Non-finance report centre and exports.',
            'is_core' => false, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('modules')->where('slug', 'reports')->delete();
    }
};
