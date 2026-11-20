@extends('directory.layout')
@section('title', $title)
@section('content')
<section class="hero directory-hero"><div class="wrap">
    <p class="breadcrumb"><a href="{{ route('directory.home') }}">Home</a> / {{ $title }}</p>
    <h1>{{ $title }}</h1><p class="intro">{{ $intro }}</p>
    <form class="search-form resource-search" method="get" action="{{ route('directory.resources', $resource) }}">
        <label>Search {{ $title }}<input name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Search by title"></label>
        @if(in_array($resource, ['courses', 'books', 'events'], true))
            <label>City<select name="city"><option value="">All cities</option>
                @foreach($cities as $city)<option value="{{ $city->slug }}" @selected(($filters['city'] ?? '') === $city->slug)>{{ $city->name }}</option>@endforeach
            </select></label>
        @endif
        @if($resource === 'books')
            <label>Listing type<select name="kind"><option value="">All types</option>
                @foreach(['sell' => 'For sale', 'swap' => 'Swap', 'donate' => 'Donation'] as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['kind'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select></label>
        @endif
        @if($subjects->isNotEmpty())
            <label>Subject<select name="subject"><option value="">All subjects</option>
                @foreach($subjects as $subject)<option value="{{ $subject }}" @selected(($filters['subject'] ?? '') === $subject)>{{ $subject }}</option>@endforeach
            </select></label>
        @endif
        @foreach(['board' => [$boards, 'Board'], 'level' => [$levels, 'Education level'], 'category' => [$categories, 'Category']] as $filter => [$options, $label])
            @if($options->isNotEmpty())<label>{{ $label }}<select name="{{ $filter }}"><option value="">Any</option>
                @foreach($options as $option)<option value="{{ $option->slug }}" @selected(($filters[$filter] ?? '') === $option->slug)>{{ $option->name }}</option>@endforeach
            </select></label>@endif
        @endforeach
        <button class="button teal">Search</button>
    </form>
</div></section>
<section class="wrap block" aria-label="{{ $title }}">
    <div class="heading"><div><h2>{{ number_format($rows->total()) }} {{ $rows->total() === 1 ? 'result' : 'results' }}</h2></div>
        @if(in_array($resource, ['books', 'study-resources'], true))
            <a class="button outline" href="{{ route('directory.resources', $resource === 'books' ? 'study-resources' : 'books') }}">{{ $resource === 'books' ? 'Browse study resources' : 'Browse books' }} →</a>
        @endif
    </div>
    <div class="card-grid">
        @forelse($rows as $row)
            @php($key = in_array($resource, ['books', 'study-resources', 'admissions'], true) ? $row->id : $row->slug)
            <article class="panel public-resource-card">
                <p class="resource-meta">{{ implode(' · ', array_filter([$row->school_name ?? $row->provider_name ?? null, $row->city_name ?? null, isset($row->event_type) ? ucfirst(str_replace('_', ' ', $row->event_type)) : ($row->subject ?? null)])) }}</p>
                <h3><a href="{{ route('directory.resource', [$resource, $key]) }}">{{ $row->title }}</a></h3>
                @if(!empty($row->starts_at))<p>{{ \Illuminate\Support\Carbon::parse($row->starts_at)->format('j M Y · g:i a') }}</p>@endif
                @if(isset($row->listing_type))<p class="resource-price">{{ $row->listing_type === 'sell' && $row->price !== null ? 'PKR '.number_format($row->price) : ucfirst($row->listing_type) }}</p>@endif
                @if(!empty($row->excerpt ?? $row->summary ?? $row->description ?? null))
                    <p>{{ Str::limit($row->excerpt ?? $row->summary ?? $row->description, 150) }}</p>
                @endif
                <a class="button outline" href="{{ route('directory.resource', [$resource, $key]) }}">View details →</a>
            </article>
        @empty
            <p class="empty">No matching records. Try a different search or check back later.</p>
        @endforelse
    </div>
    @if($rows->hasPages())
        <nav class="public-pagination" aria-label="Results pages">
            @if($rows->previousPageUrl())<a class="button outline" href="{{ $rows->previousPageUrl() }}">Previous</a>@endif
            <span>Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}</span>
            @if($rows->nextPageUrl())<a class="button outline" href="{{ $rows->nextPageUrl() }}">Next</a>@endif
        </nav>
    @endif
</section>
@endsection
