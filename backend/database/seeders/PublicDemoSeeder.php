<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PublicDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Public demo content can only be seeded locally or in tests.');
        }

        $this->call(DirectoryDemoSeeder::class);
        $campus = School::publicDirectory()->where('slug', 'directory-demo-school-1')->firstOrFail();
        $account = User::query()->firstOrCreate(
            ['email' => 'directory-content-demo@example.test'],
            ['name' => 'TaleemFinder Demo Content', 'password' => Str::random(32)],
        );
        $city = DB::table('cities')->where('slug', 'islamabad')->value('id');
        $level = DB::table('education_levels')->where('slug', 'primary')->value('id');
        $category = DB::table('course_categories')->where('slug', 'information-technology')->value('id');

        foreach ([
            'graphic-designing-course-in-islamabad' => ['Graphic Design', 'Learn layout, typography and basic poster design.'],
            'python-course-in-islamabad' => ['Python Fundamentals', 'Practise variables, loops and small programs.'],
            'typing-and-shorthand-course-in-islamabad' => ['Typing and Shorthand', 'Build keyboard accuracy and practise note-taking.'],
        ] as $slug => [$name, $summary]) {
            $this->insertIfMissing('courses', ['slug' => $slug], [
                'city_id' => $city, 'provider_name' => 'Demo Learning Centre', 'course_category_id' => $category,
                'title' => $name.' (Demo)', 'summary' => $summary, 'duration_value' => 6, 'duration_unit' => 'weeks',
                'mode' => 'hybrid', 'fee' => '4500.00', 'status' => 'published',
            ]);
        }

        foreach ([
            'demo-campus-open-day' => ['Campus Open Day (Demo)', 'open_house', 14],
            'demo-admissions-question-session' => ['Admissions Questions (Demo)', 'admission_drive', 21],
            'demo-study-skills-seminar' => ['Study Skills Seminar (Demo)', 'seminar', 28],
        ] as $slug => [$title, $type, $days]) {
            $this->insertIfMissing('events', ['slug' => $slug], [
                'school_id' => $campus->id, 'city_id' => $city, 'title' => $title, 'event_type' => $type,
                'description' => 'A fictional event for trying the local events directory and detail page.',
                'starts_at' => today()->addDays($days)->setHour(10), 'venue' => 'Demo campus', 'status' => 'published',
            ]);
        }

        foreach ([
            'demo-campus-visit-checklist' => ['A campus visit checklist', 'Ask about the school day, transport, classroom routines and the availability of support.'],
            'demo-compare-education-costs' => ['Comparing school costs', 'Ask for a breakdown of tuition, admission fees, transport and any necessary materials.'],
            'demo-prepare-for-admissions' => ['Preparing for admissions', 'Confirm deadlines and required documents directly with the school. Keep a copy of anything you submit.'],
        ] as $slug => [$title, $body]) {
            $this->insertIfMissing('articles', ['slug' => $slug], [
                'title' => $title.' (Demo)', 'excerpt' => $body, 'body' => $body, 'author_user_id' => $account->id,
                'status' => 'published', 'site' => 'finder', 'published_at' => now(),
            ]);
        }

        foreach ([
            ['Mathematics Practice Workbook (Demo)', 'Mathematics', 'sell', '350.00'],
            ['Everyday Science Activities (Demo)', 'Science', 'swap', null],
            ['English Reading Exercises (Demo)', 'English', 'donate', null],
        ] as [$title, $subject, $type, $price]) {
            $this->insertIfMissing('books', ['user_id' => $account->id, 'title' => $title], [
                'city_id' => $city, 'subject' => $subject, 'education_level_id' => $level, 'condition' => 'good',
                'listing_type' => $type, 'price' => $price, 'description' => 'Example exchange listing for local testing.', 'status' => 'available',
            ]);
        }

        foreach ([
            'DemoNumberPractice' => ['Number Practice Sheet (Demo)', 'Mathematics', 'Try these sums: 8 + 7, 20 - 9, and 4 x 6.'],
            'DemoReadingChecklist' => ['Reading Checklist (Demo)', 'English', 'After reading, list the characters and describe the main idea in one sentence.'],
        ] as $file => [$title, $subject, $text]) {
            $path = "study-files/{$file}.pdf";
            if (! Storage::disk('local')->exists($path) && ! Storage::disk('local')->put($path, $this->examplePdf($text))) {
                throw new RuntimeException('Could not save the demo study PDF.');
            }
            $this->insertIfMissing('study_resources', ['user_id' => $account->id, 'title' => $title], [
                'school_id' => $campus->id, 'subject' => $subject, 'education_level_id' => $level,
                'description' => 'Original practice material created for the local demo.', 'file_path' => $path, 'status' => 'approved',
            ]);
        }

        $brochure = 'listing-media/DemoCampusBrochure.pdf';
        if (! Storage::disk('public')->exists($brochure) && ! Storage::disk('public')->put($brochure,
            $this->examplePdf('Demo campus brochure. Confirm real campus information directly with the institution.'))) {
            throw new RuntimeException('Could not save the demo campus brochure.');
        }
        $this->insertIfMissing('school_media', ['school_id' => $campus->id, 'path' => $brochure], [
            'kind' => 'brochure', 'caption' => 'Campus brochure (Demo)', 'is_approved' => true,
            'uploaded_by_user_id' => $account->id,
        ]);

        $this->insertIfMissing('reviews', ['school_id' => $campus->id, 'user_id' => $account->id, 'title' => 'Example review (Demo)'], [
            'rating' => 4, 'body' => 'A fictional review demonstrating how moderated feedback appears on a campus profile.',
            'relationship' => 'parent', 'status' => 'approved', 'moderated_at' => now(),
        ]);
    }

    private function insertIfMissing(string $table, array $key, array $values): void
    {
        if (! DB::table($table)->where($key)->exists()) {
            DB::table($table)->insert([...$key, ...$values, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function examplePdf(string $text): string
    {
        $stream = "BT /F1 12 Tf 46 755 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $start = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$start}\n%%EOF\n";
    }
}
