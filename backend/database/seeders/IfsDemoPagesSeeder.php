<?php

namespace Database\Seeders;

use App\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IfsDemoPagesSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('IFS demo pages are limited to local and testing environments.');
        }

        DB::transaction(function (): void {
            $branch = School::query()->where('slug', 'ifs')->lockForUpdate()->firstOrFail();
            $pages = [
                'home' => ['Home', 'Welcome to IFS', 'This is a sample home page for trying out the school website editor.',
                    ['Use the menu to explore the other sample pages.', 'Edit the page blocks in School pages to try your own content.']],
                'about' => ['About IFS', 'Our school', 'This draft shows how an introduction and a short list can be combined.',
                    ['Introduce the school and its community.', 'Add a short summary of the school’s approach to learning.']],
                'admissions' => ['Admissions', 'How to enquire', 'These are example admissions steps for the preview.',
                    ['Ask the school about places for the academic year.', 'Arrange a visit if available.', 'Confirm the application process with the school.']],
                'contact' => ['Contact', 'Get in touch', 'Use this page to display confirmed contact details and visiting hours once they are available.', []],
            ];

            foreach ($pages as $slug => [$title, $heading, $paragraph, $items]) {
                $blocks = [
                    ['type' => 'heading', 'text' => $heading],
                    ['type' => 'paragraph', 'text' => $paragraph],
                ];
                if ($items !== []) {
                    $blocks[] = ['type' => 'list', 'items' => $items];
                }

                $key = ['school_id' => $branch->id, 'slug' => $slug];
                if (! DB::table('school_pages')->where($key)->exists()) {
                    DB::table('school_pages')->insert([
                        ...$key, 'title' => $title, 'page_type' => $slug, 'content' => json_encode($blocks, JSON_THROW_ON_ERROR),
                        'show_in_menu' => true, 'sort_order' => array_search($slug, array_keys($pages), true),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });
    }
}
