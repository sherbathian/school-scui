<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IfsSiteThemeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('site_themes')->insertOrIgnore([
            'name' => 'IFS',
            'slug' => 'ifs',
            'description' => 'A deep green and cream school theme with prominent admissions and campus photography.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
