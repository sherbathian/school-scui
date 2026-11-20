<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicResourcesController extends Controller
{
    public const PAGES = [
        'courses' => ['Short courses', 'Explore practical training and skills programmes.'],
        'admissions' => ['Admissions', 'Explore programmes currently accepting enquiries.'],
        'books' => ['Books & study resources', 'Find books to buy, swap or receive as a donation.'],
        'study-resources' => ['Study resources', 'Browse approved learning materials by subject.'],
        'events' => ['Education events', 'Find upcoming open days, entry tests and seminars.'],
        'parent-guides' => ['Parent guides', 'Practical advice for comparing schools and planning admissions.'],
    ];

    public function index(Request $request, string $resource): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'subject' => ['nullable', 'string', 'max:80'],
            'board' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(['sell', 'swap', 'donate'])],
        ]);
        $query = $this->query($resource);
        if (! empty($filters['q'])) {
            $query->whereLike('record.title', '%'.$filters['q'].'%');
        }
        if (! empty($filters['city']) && in_array($resource, ['courses', 'books', 'events'], true)) {
            $query->where('location.slug', $filters['city']);
        }
        if ($resource === 'books' && ! empty($filters['kind'])) {
            $query->where('record.listing_type', $filters['kind']);
        }
        if (in_array($resource, ['books', 'study-resources'], true)) {
            $query->when(! empty($filters['subject']), fn (Builder $selection) => $selection->where('record.subject', $filters['subject']))
                ->when(! empty($filters['level']), fn (Builder $selection) => $selection->where('education_levels.slug', $filters['level']));
        }
        if ($resource === 'books' && ! empty($filters['board'])) {
            $query->where('boards.slug', $filters['board']);
        }
        if ($resource === 'courses' && ! empty($filters['category'])) {
            $query->where('course_categories.slug', $filters['category']);
        }
        $rows = $query->orderBy($resource === 'events' ? 'record.starts_at' : 'record.title')
            ->orderBy('record.id')->paginate(12)->withQueryString();

        return view('directory.resources', [
            'resource' => $resource, 'rows' => $rows, 'filters' => $filters,
            'cities' => DB::table('cities')->where('is_active', true)->orderBy('name')->get(['name', 'slug']),
            'subjects' => in_array($resource, ['books', 'study-resources'], true)
                ? $this->query($resource)->whereNotNull('record.subject')->select('record.subject')->distinct()->orderBy('record.subject')->pluck('record.subject') : collect(),
            'boards' => $resource === 'books' ? DB::table('boards')->where('is_active', true)->orderBy('name')->get(['name', 'slug']) : collect(),
            'levels' => in_array($resource, ['books', 'study-resources'], true)
                ? DB::table('education_levels')->orderBy('sort_order')->orderBy('name')->get(['name', 'slug']) : collect(),
            'categories' => $resource === 'courses' ? DB::table('course_categories')->orderBy('sort_order')->orderBy('name')->get(['name', 'slug']) : collect(),
            'title' => self::PAGES[$resource][0], 'intro' => self::PAGES[$resource][1],
        ]);
    }

    public function show(string $resource, string $record): View
    {
        $key = in_array($resource, ['books', 'study-resources', 'admissions'], true) ? 'id' : 'slug';
        abort_if($key === 'id' && ! ctype_digit($record), 404);

        return view('directory.resource-detail', [
            'resource' => $resource, 'row' => $this->query($resource)->where("record.{$key}", $record)->firstOrFail(),
            'collectionTitle' => self::PAGES[$resource][0],
        ]);
    }

    public function download(int $record): StreamedResponse
    {
        $row = $this->query('study-resources')->where('record.id', $record)->firstOrFail();
        $storage = Storage::disk('local');
        abort_unless(preg_match('~\Astudy-files/[A-Za-z0-9]+\.pdf\z~', $row->file_path) && $storage->exists($row->file_path), 404);
        DB::table('study_resources')->where('id', $record)->where('status', 'approved')->increment('download_count');

        return $storage->download($row->file_path, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function policy(string $topic): View
    {
        [$title, $paragraphs] = match ($topic) {
            'community-privacy' => ['Community privacy', [
                'Keep personal contact details out of public descriptions, reviews and uploaded documents.',
                'Only submit a school enquiry if you agree that the selected institution may receive the contact details you provide.',
                'Contact support to request a correction or removal of information shown in the directory.',
            ]],
            'terms-of-use' => ['Terms of use', [
                'Directory information can change. Confirm fees, availability and admission dates directly with the institution.',
                'Use community features to share accurate information and material you have permission to share.',
                'Contact support to report misleading listings or inappropriate material.',
            ]],
            'copyright-takedown' => ['Copyright and takedown', [
                'Share only material you own or have permission to distribute.',
                'To report material, send support the directory URL, a description of the work and enough information for us to review your request.',
            ]],
        };

        return view('directory.policy', compact('title', 'paragraphs'));
    }

    public function enquireBook(Request $request, int $book): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $record = $this->query('books')->where('record.id', $book)->firstOrFail();
        abort_if((int) $record->user_id === (int) $request->user()->id, 422, 'This book belongs to your account.');
        abort_unless($request->user()->is_active, 403);
        DB::transaction(function () use ($request, $book, $data): void {
            DB::table('books')->where('id', $book)->lockForUpdate()->firstOrFail();
            $this->query('books')->where('record.id', $book)->firstOrFail();
            $key = ['book_id' => $book, 'user_id' => $request->user()->id, 'status' => 'open'];
            if (! DB::table('book_inquiries')->where($key)->exists()) {
                DB::table('book_inquiries')->insert([...$key, 'message' => $data['message'], 'created_at' => now(), 'updated_at' => now()]);
            }
        }, 3);

        return back()->with('directory_message', 'Your enquiry is in the book exchange queue.');
    }

    private function query(string $resource): Builder
    {
        $table = match ($resource) {
            'courses' => 'courses', 'admissions' => 'programs', 'events' => 'events',
            'books' => 'books', 'study-resources' => 'study_resources', 'parent-guides' => 'articles',
        };
        $query = DB::table("{$table} as record")->select('record.*');
        if (in_array($resource, ['courses', 'events', 'books'], true)) {
            $query->leftJoin('cities as location', 'location.id', '=', 'record.city_id')->addSelect('location.name as city_name');
        }
        if (in_array($resource, ['courses', 'events', 'admissions', 'study-resources'], true)) {
            $visible = School::publicDirectory()->select('schools.id');
            $query->leftJoin('schools as campus', 'campus.id', '=', 'record.school_id')->addSelect('campus.name as school_name', 'campus.slug as school_slug')
                ->where(fn (Builder $scope) => $scope->whereNull('record.school_id')->orWhereIn('record.school_id', $visible));
        }

        if (in_array($resource, ['books', 'study-resources'], true)) {
            $query->leftJoin('education_levels', 'education_levels.id', '=', 'record.education_level_id')
                ->addSelect('education_levels.name as education_level');
        }
        if ($resource === 'books') {
            $query->leftJoin('boards', fn ($join) => $join->on('boards.id', '=', 'record.board_id')->where('boards.is_active', true))
                ->addSelect('boards.name as board_name');
        }
        if ($resource === 'courses') {
            $query->leftJoin('course_categories', 'course_categories.id', '=', 'record.course_category_id')
                ->addSelect('course_categories.name as category_name');
        }

        return match ($resource) {
            'books' => $query->where('record.status', 'available')->where(fn (Builder $date) => $date
                ->whereNull('record.expires_at')->orWhere('record.expires_at', '>', now())),
            'study-resources' => $query->where('record.status', 'approved'),
            'admissions' => $query->where('record.status', 'active')->where('record.admission_open', true),
            'events' => $query->where('record.status', 'published')->where(fn (Builder $date) => $date
                ->where('record.ends_at', '>=', now())->orWhere(fn (Builder $start) => $start
                ->whereNull('record.ends_at')->where('record.starts_at', '>=', now()))),
            'parent-guides' => $query->where('record.status', 'published')->where('record.site', 'finder')
                ->whereNotNull('record.published_at')->where('record.published_at', '<=', now()),
            default => $query->where('record.status', 'published'),
        };
    }
}
