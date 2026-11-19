<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCommunityController extends AdminPlatformController
{
    public const RESOURCES = [
        'events' => 'events',
        'listing-leads' => 'listing_leads',
        'reviews' => 'reviews',
        'saved-schools' => 'saved_schools',
        'school-stats' => 'school_stats_daily',
        'articles' => 'articles',
        'student-transfers' => 'student_transfers',
        'student-fee-discounts' => 'student_fee_discounts',
        'books' => 'books',
        'book-inquiries' => 'book_inquiries',
        'study-resources' => 'study_resources',
    ];

    protected const PAIRS = ['saved-schools' => ['user_id', 'school_id']];

    private const UPLOADS = [
        'books' => ['photo_path', 'book-photos', 'jpg,jpeg,png,webp'],
        'study-resources' => ['file_path', 'study-files', 'pdf'],
    ];

    public function store(Request $request, string $resource): JsonResponse
    {
        return $this->saveAttachment($request, $resource, fn () => parent::store($request, $resource));
    }

    public function update(Request $request, string $resource, string $record): JsonResponse
    {
        return $this->saveAttachment($request, $resource, fn () => parent::update($request, $resource, $record));
    }

    public function download(string $resource, int $record): StreamedResponse
    {
        [$column] = self::UPLOADS[$resource];
        $path = DB::table(self::RESOURCES[$resource])->where('id', $record)->firstOrFail()->$column;
        $disk = Storage::disk('local');
        abort_unless($this->isManagedAttachment($resource, $path) && $disk->exists($path), 404);

        return $disk->download($path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function saveAttachment(Request $request, string $resource, callable $save): JsonResponse
    {
        if (! isset(self::UPLOADS[$resource]) || ! $request->has('file')) {
            return $save();
        }

        [$column, $directory, $extensions] = self::UPLOADS[$resource];
        $file = $request->validate(['file' => ['required', 'file', 'mimes:'.$extensions, 'max:10240']])['file'];
        $path = $file->store($directory, 'local');
        abort_unless($path, 500, 'The attachment could not be saved.');
        $request->merge([$column => $path]);

        try {
            return $save();
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);

            throw $error;
        }
    }

    private function isManagedAttachment(string $resource, ?string $path): bool
    {
        if ($path === null || ! isset(self::UPLOADS[$resource])) {
            return false;
        }
        [, $directory, $extensions] = self::UPLOADS[$resource];

        return (bool) preg_match('~\A'.$directory.'/[A-Za-z0-9]+\.(?:'.str_replace(',', '|', $extensions).')\z~', $path);
    }

    private function removeUnusedAttachment(string $resource, ?string $path): void
    {
        if (! $this->isManagedAttachment($resource, $path)) {
            return;
        }
        [$column] = self::UPLOADS[$resource];
        DB::afterCommit(function () use ($resource, $column, $path): void {
            if (! DB::table(self::RESOURCES[$resource])->where($column, $path)->exists()) {
                Storage::disk('local')->delete($path);
            }
        });
    }

    public function options(string $resource): JsonResponse
    {
        $tables = match ($resource) {
            'books' => ['users' => 'name', 'cities' => 'name', 'areas' => 'name', 'boards' => 'name', 'education_levels' => 'name'],
            'book-inquiries' => ['books' => 'title', 'users' => 'name'],
            'study-resources' => ['users' => 'name', 'schools' => 'name', 'education_levels' => 'name'],
            'events' => ['schools' => 'name', 'cities' => 'name'],
            'listing-leads' => ['schools' => 'name', 'users' => 'name', 'student_inquiries' => 'first_name'],
            'reviews', 'saved-schools' => ['schools' => 'name', 'users' => 'name'],
            'school-stats' => ['schools' => 'name'],
            'student-transfers' => ['schools' => 'name', 'students' => 'admission_number'],
            'student-fee-discounts' => ['schools' => 'name', 'students' => 'admission_number', 'fee_discounts' => 'title', 'school_sessions' => 'title'],
            default => [],
        };
        $options = [];
        foreach ($tables as $table => $label) {
            $query = DB::table($table)->orderBy($label);
            $columns = ['id', "{$label} as name"];
            if ($table === 'areas') {
                $columns[] = 'city_id';
            }
            if ($table === 'schools') {
                $query->whereNull('deleted_at');
            }
            if (in_array($table, ['students', 'student_inquiries', 'fee_discounts', 'school_sessions'], true)) {
                $columns[] = 'school_id';
            }
            if (in_array($table, ['students', 'student_inquiries'], true)) {
                $columns[] = 'first_name';
                $columns[] = 'last_name';
            }
            $options[$table] = $query->get($columns)->map(function (object $item) use ($table): object {
                if ($table === 'students') {
                    $item->name = "{$item->name} · {$item->first_name} {$item->last_name}";
                } elseif ($table === 'student_inquiries') {
                    $item->name = "{$item->first_name} {$item->last_name} (#{$item->id})";
                }

                return $item;
            });
        }

        return response()->json(['data' => $options]);
    }

    protected function prepare(Request $request, string $resource, ?object $current, array $data): array
    {
        $values = $data + (array) $current;
        if (isset(self::UPLOADS[$resource])) {
            [$column] = self::UPLOADS[$resource];
            if ($this->isManagedAttachment($resource, $data[$column] ?? null)
                && $data[$column] !== ($current?->$column ?? null) && ! $request->hasFile('file')) {
                throw ValidationException::withMessages([$column => 'Upload the file to create a managed attachment.']);
            }
        }
        if ($resource === 'books') {
            if (isset($values['area_id']) && ! DB::table('areas')->where('id', $values['area_id'])
                ->where('city_id', $values['city_id'])->exists()) {
                throw ValidationException::withMessages(['area_id' => 'Choose an area in the selected city.']);
            }
            if (isset($values['price']) && ($values['listing_type'] ?? 'sell') !== 'sell') {
                throw ValidationException::withMessages(['price' => 'A price applies only to books listed for sale.']);
            }
        }
        if ($resource === 'events') {
            $this->ordered($values, 'starts_at', 'ends_at');
            if (! $current) {
                $data['created_by_user_id'] = $request->user()->id;
            }
        }
        if ($resource === 'articles') {
            if (! $current) {
                $data['author_user_id'] = $request->user()->id;
            }
            if (($values['status'] ?? null) === 'published' && empty($values['published_at'])) {
                $data['published_at'] = now();
            }
        }
        if ($resource === 'reviews') {
            if (isset($data['status']) && $data['status'] !== ($current->status ?? 'pending')) {
                $data['moderated_by_user_id'] = $request->user()->id;
                $data['moderated_at'] = now();
            }
            if (array_key_exists('school_reply', $data) && $data['school_reply'] !== ($current->school_reply ?? null)) {
                $data['replied_by_user_id'] = $data['school_reply'] === null ? null : $request->user()->id;
                $data['school_replied_at'] = $data['school_reply'] === null ? null : now();
            }
        }
        if ($resource === 'listing-leads') {
            if ($current && (int) $values['school_id'] !== (int) $current->school_id) {
                throw ValidationException::withMessages(['school_id' => 'A lead cannot be moved to another branch.']);
            }
            if (! empty($values['student_inquiry_id'])) {
                $inquiry = DB::table('student_inquiries')->where('school_id', $values['school_id'])
                    ->where('id', $values['student_inquiry_id'])->lockForUpdate()->first();
                if (empty($values['consent_given']) || ! $inquiry || ($inquiry->listing_lead_id && (int) $inquiry->listing_lead_id !== (int) $current?->id)) {
                    throw ValidationException::withMessages(['student_inquiry_id' => 'Choose an unlinked inquiry in the same branch and record consent first.']);
                }
            }
        }
        if ($resource === 'student-transfers') {
            if ((int) $values['from_school_id'] === (int) $values['to_school_id']) {
                throw ValidationException::withMessages(['to_school_id' => 'Choose a different destination branch.']);
            }
            $this->sameBranch($values, 'students', 'from_student_id', 'from_school_id');
            $this->sameBranch($values, 'students', 'to_student_id', 'to_school_id');
            if (! $current) {
                $data['transferred_by_user_id'] = $request->user()->id;
            }
        }
        if ($resource === 'student-fee-discounts') {
            $this->sameBranch($values, 'students', 'student_id');
            $this->sameBranch($values, 'fee_discounts', 'fee_discount_id');
            $this->sameBranch($values, 'school_sessions', 'school_session_id');
            $this->ordered($values, 'starts_on', 'ends_on');
        }

        return $data;
    }

    protected function beforeDelete(string $resource, object $record): void
    {
        if (isset(self::UPLOADS[$resource])) {
            [$column] = self::UPLOADS[$resource];
            $this->removeUnusedAttachment($resource, $record->$column);
        }
        if ($resource === 'listing-leads') {
            $this->unlinkInquiry($record->id);
        }
    }

    protected function afterSave(string $resource, object $record, ?object $previous): void
    {
        if ($previous && isset(self::UPLOADS[$resource])) {
            [$column] = self::UPLOADS[$resource];
            if ($previous->$column !== $record->$column) {
                $this->removeUnusedAttachment($resource, $previous->$column);
            }
        }
        if ($resource === 'listing-leads' && $record->student_inquiry_id !== $previous?->student_inquiry_id) {
            $this->unlinkInquiry($record->id);
            if ($record->student_inquiry_id) {
                DB::table('student_inquiries')->where('school_id', $record->school_id)->where('id', $record->student_inquiry_id)
                    ->update(['listing_lead_id' => $record->id, 'updated_at' => now()]);
            }
        }
    }

    private function unlinkInquiry(int $lead): void
    {
        DB::table('student_inquiries')->where('listing_lead_id', $lead)->update(['listing_lead_id' => null, 'updated_at' => now()]);
    }

    private function sameBranch(array $values, string $table, string $field, string $school = 'school_id'): void
    {
        if (isset($values[$field]) && ! DB::table($table)->where('id', $values[$field])->where('school_id', $values[$school])->exists()) {
            throw ValidationException::withMessages([$field => 'Choose a record in the selected branch.']);
        }
    }

    private function ordered(array $values, string $start, string $end): void
    {
        if (isset($values[$start], $values[$end]) && $values[$start] > $values[$end]) {
            throw ValidationException::withMessages([$end => 'The end cannot be before the start.']);
        }
    }

    protected function rules(string $resource): array
    {
        $text = fn (int $max, bool $required = false) => [$required ? 'required' : 'nullable', 'string', "max:{$max}"];
        $id = fn (string $table, bool $required = true) => [$required ? 'required' : 'nullable', 'integer',
            $table === 'schools' ? Rule::exists($table, 'id')->whereNull('deleted_at') : Rule::exists($table, 'id')];
        $slug = fn (int $max) => ['required', 'alpha_dash:ascii', "max:{$max}"];
        $count = ['integer', 'between:0,2147483647'];
        $day = ['nullable', 'date_format:Y-m-d'];
        $email = ['nullable', 'email', 'max:190'];
        $note = $text(16000);

        $storagePath = ['string', 'max:255', 'regex:~\A(?!/)(?!.*(?:^|/)\.\.(?:/|$))[a-zA-Z0-9_./-]+\z~'];

        return match ($resource) {
            'books' => [
                'user_id' => $id('users'), 'city_id' => $id('cities'), 'area_id' => $id('areas', false), 'title' => $text(200, true),
                'author' => $text(150), 'subject' => $text(80), 'education_level_id' => $id('education_levels', false),
                'board_id' => $id('boards', false), 'condition' => [Rule::in(['new', 'good', 'fair', 'worn'])],
                'listing_type' => [Rule::in(['sell', 'swap', 'donate'])], 'price' => ['nullable', 'decimal:0,2', 'between:0,99999999.99'],
                'description' => $note, 'photo_path' => ['nullable', ...$storagePath],
                'status' => [Rule::in(['available', 'reserved', 'given', 'removed'])], 'expires_at' => ['nullable', 'date'],
            ],
            'book-inquiries' => [
                'book_id' => $id('books'), 'user_id' => $id('users'), 'message' => $text(16000, true),
                'status' => [Rule::in(['open', 'accepted', 'declined'])],
            ],
            'study-resources' => [
                'user_id' => $id('users'), 'school_id' => $id('schools', false), 'title' => $text(200, true), 'subject' => $text(80),
                'education_level_id' => $id('education_levels', false), 'description' => $note,
                'file_path' => ['required', ...$storagePath], 'download_count' => $count,
                'status' => [Rule::in(['pending', 'approved', 'rejected'])],
            ],
            'events' => [
                'school_id' => $id('schools', false), 'title' => $text(180, true), 'slug' => $slug(200),
                'event_type' => [Rule::in(['open_house', 'entry_test', 'admission_drive', 'seminar', 'other'])],
                'description' => $note, 'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date'],
                'venue' => $text(200), 'city_id' => $id('cities', false), 'registration_url' => ['nullable', 'url:http,https', 'max:500'],
                'status' => [Rule::in(['draft', 'published', 'cancelled'])],
            ],
            'listing-leads' => [
                'school_id' => $id('schools'), 'user_id' => $id('users', false), 'parent_name' => $text(150, true),
                'phone' => $text(30, true), 'email' => $email, 'grade_interested' => $text(60), 'message' => $note,
                'source' => $text(30, true), 'status' => [Rule::in(['new', 'forwarded', 'contacted', 'closed', 'spam'])],
                'student_inquiry_id' => $id('student_inquiries', false), 'consent_given' => ['boolean'],
            ],
            'reviews' => [
                'school_id' => $id('schools'), 'user_id' => $id('users'), 'rating' => ['required', 'integer', 'between:1,5'],
                'title' => $text(150), 'body' => $text(16000, true), 'relationship' => [Rule::in(['parent', 'student', 'alumni'])],
                'status' => [Rule::in(['pending', 'approved', 'rejected'])], 'school_reply' => $note,
            ],
            'saved-schools' => ['user_id' => $id('users'), 'school_id' => $id('schools')],
            'school-stats' => [
                'school_id' => $id('schools'), 'stat_date' => ['required', 'date_format:Y-m-d'],
                'profile_views' => $count, 'saves' => $count, 'compare_adds' => $count, 'leads' => $count,
            ],
            'articles' => [
                'title' => $text(200, true), 'slug' => $slug(220), 'excerpt' => $text(300), 'body' => $text(100000, true),
                'cover_path' => ['nullable', 'string', 'max:255', 'regex:~\A(?!/)(?!.*(?:^|/)\.\.(?:/|$))[a-zA-Z0-9_./-]+\z~'],
                'status' => [Rule::in(['draft', 'published'])], 'published_at' => ['nullable', 'date'],
                'seo_title' => $text(160), 'seo_description' => $text(300), 'site' => [Rule::in(['finder', 'marketing'])],
            ],
            'student-transfers' => [
                'from_school_id' => $id('schools'), 'to_school_id' => $id('schools'),
                'from_student_id' => $id('students'), 'to_student_id' => $id('students'),
                'transfer_date' => ['required', 'date_format:Y-m-d'], 'reason' => $text(255),
            ],
            'student-fee-discounts' => [
                'school_id' => $id('schools'), 'student_id' => $id('students'), 'fee_discount_id' => $id('fee_discounts'),
                'school_session_id' => $id('school_sessions', false), 'starts_on' => $day, 'ends_on' => $day, 'note' => $text(255),
            ],
        };
    }
}
