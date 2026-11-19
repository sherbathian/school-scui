<?php

namespace Database\Seeders;

use App\Models\School;
use App\Services\SchoolOrganisations;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DirectoryDemoSeeder extends Seeder
{
    public const PREFIX = 'directory-demo-';

    public function run(SchoolOrganisations $organisations): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Directory demo data is limited to local and testing environments.');
        }

        DB::transaction(function () use ($organisations): void {
            $locations = [];
            foreach (['Islamabad' => ['F-8', 'G-10', 'I-8'], 'Rawalpindi' => ['Satellite Town', 'Chaklala', 'Saddar']] as $city => $areas) {
                $cityId = $this->lookup('cities', $city, ['province' => $city === 'Islamabad' ? 'Islamabad Capital Territory' : 'Punjab']);
                foreach ($areas as $area) {
                    $locations[] = [$city, $area, $cityId, $this->lookup('areas', $area, ['city_id' => $cityId], ['city_id' => $cityId])];
                }
            }

            $boards = [
                $this->lookup('boards', 'Federal Board'),
                $this->lookup('boards', 'Cambridge', ['kind' => 'curriculum']),
            ];
            $levels = [
                'school' => $this->lookup('education_levels', 'Primary'),
                'college' => $this->lookup('education_levels', 'Intermediate'),
                'university' => $this->lookup('education_levels', 'Bachelor'),
                'training_institute' => $this->lookup('education_levels', 'Certificate'),
            ];
            $facilities = array_map(fn (string $name) => $this->lookup('facilities', $name), ['Library', 'Computer lab', 'Sports ground', 'Student transport']);
            $categories = array_map(fn (string $name) => $this->lookup('course_categories', $name), ['Information Technology', 'Languages', 'Business Skills']);
            $names = ['Cedar', 'Summit', 'Maple', 'Horizon', 'Oakridge', 'Meadow', 'Silverleaf', 'Pinecrest', 'Riverstone', 'Hillview'];
            $types = [
                'school' => [30, 'School', 4500],
                'college' => [20, 'College', 8500],
                'university' => [10, 'University', 16000],
                'training_institute' => [10, 'Training Institute', 6500],
            ];

            foreach ($types as $type => [$count, $label, $baseFee]) {
                for ($index = 0; $index < $count; $index++) {
                    [$city, $area, $cityId, $areaId] = $locations[$index % count($locations)];
                    $name = $names[$index % count($names)].' '.$label.' · '.$area.' (Demo)';
                    $slug = self::PREFIX.Str::slug($type).'-'.($index + 1);
                    $school = School::withTrashed()->firstOrCreate(['slug' => $slug], ['name' => $name, 'status' => 'active']);
                    if (! $school->wasRecentlyCreated) {
                        continue;
                    }

                    $fee = $baseFee + ($index % 7) * 250;
                    $school->forceFill([
                        'institute_type' => $type, 'city_id' => $cityId, 'area_id' => $areaId,
                        'branch_name' => $area, 'address_line' => "{$area}, {$city}",
                        'tagline' => 'Synthetic demo listing',
                        'description' => 'Sample profile for testing the directory. Campus details, fees and programmes are fictional.',
                        'listing_status' => 'published', 'source' => 'admin_created',
                        'verified_at' => now(), 'published_at' => now(),
                    ])->save();
                    $organisations->ensureFor($school);

                    DB::table('school_education_level')->insert(['school_id' => $school->id, 'education_level_id' => $levels[$type]]);
                    if (in_array($type, ['school', 'college'], true)) {
                        DB::table('school_board')->insert(['school_id' => $school->id, 'board_id' => $boards[$index % 2]]);
                    }
                    foreach ([$facilities[$index % 4], $facilities[($index + 1) % 4]] as $facility) {
                        DB::table('school_facility')->insert(['school_id' => $school->id, 'facility_id' => $facility]);
                    }
                    $this->insert('school_listing_fees', [
                        'school_id' => $school->id, 'education_level_id' => $levels[$type], 'label' => 'Indicative demo tuition',
                        'monthly_fee' => $fee, 'admission_fee' => $fee * 2,
                        'effective_from' => now()->toDateString(), 'last_confirmed_at' => now(), 'notes' => 'Synthetic data',
                    ]);

                    if (in_array($type, ['college', 'university'], true)) {
                        $title = $type === 'college' ? ['Pre-Engineering', 'Computer Science', 'Commerce'][$index % 3]
                            : ['BS Computer Science', 'BBA', 'BS Mathematics'][$index % 3];
                        $this->insert('programs', [
                            'school_id' => $school->id, 'title' => $title, 'slug' => Str::slug($title),
                            'level' => $type === 'college' ? 'intermediate' : 'bachelor',
                            'duration_months' => $type === 'college' ? 24 : 48,
                            'fee_amount' => $fee, 'fee_period' => 'month', 'admission_open' => $index % 3 !== 0,
                        ]);
                    }
                    if ($type === 'training_institute') {
                        $title = ['Web Development', 'Spoken English', 'Office Administration'][$index % 3];
                        $this->insert('courses', [
                            'school_id' => $school->id, 'city_id' => $cityId, 'area_id' => $areaId,
                            'course_category_id' => $categories[$index % 3], 'title' => $title,
                            'slug' => $slug.'-'.Str::slug($title), 'duration_value' => 8, 'duration_unit' => 'weeks',
                            'mode' => $index % 2 === 0 ? 'onsite' : 'hybrid', 'fee' => $fee, 'status' => 'published',
                        ]);
                    }
                }
            }
        });
    }

    private function lookup(string $table, string $name, array $defaults = [], array $scope = []): int
    {
        $slug = Str::slug($name);
        $existing = DB::table($table)->where($scope)->where('slug', $slug)->value('id');

        return $existing ?? $this->insert($table, [...$defaults, 'name' => $name, 'slug' => $slug]);
    }

    private function insert(string $table, array $data): int
    {
        return DB::table($table)->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);
    }
}
