@extends('directory.layout')
@section('title', $title)
@section('content')
@php($action = $category ? route('directory.category', $category) : route('directory.index'))
<section class="hero directory-hero"><div class="wrap">
    <p class="breadcrumb"><a href="{{ route('directory.home') }}">Home</a> / <span aria-current="page">{{ $categoryLabel ?? 'Directory' }}</span></p>
    <h1>{{ $title }}</h1>
    <p class="intro">Explore verified campuses by city, neighbourhood and institution type.</p>
    @include('directory.search')
</div></section>
<div class="wrap directory-grid">
    <aside class="filters"><h2>Filter directory</h2>
        <form action="{{ $action }}" method="get">
            <input type="hidden" name="view" value="{{ $filters['view'] ?? 'list' }}">
            <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
            <label>City<select name="city"><option value="">All cities</option>@foreach($cities as $city)
                <option value="{{ $city->slug }}" @selected(($filters['city'] ?? '') === $city->slug)>{{ $city->name }}</option>
            @endforeach</select></label>
            @if($areas->isNotEmpty())<label>Neighbourhood<select name="area"><option value="">All neighbourhoods</option>
                @foreach($areas as $area)<option value="{{ $area->slug }}" @selected(($filters['area'] ?? '') === $area->slug)>{{ $area->name }}</option>@endforeach
            </select></label>@endif
            <label>Curriculum or board<select name="board"><option value="">All curricula</option>@foreach($boards as $board)
                <option value="{{ $board->slug }}" @selected(($filters['board'] ?? '') === $board->slug)>{{ $board->name }}</option>
            @endforeach</select></label>
            <label>Education level<select name="level"><option value="">All levels</option>@foreach($levels as $level)
                <option value="{{ $level->slug }}" @selected(($filters['level'] ?? '') === $level->slug)>{{ $level->name }}</option>
            @endforeach</select></label>
            <label>Fee information<select name="has_fees"><option value="">Any listing</option><option value="1" @selected(!empty($filters['has_fees']))>Monthly fee published</option></select></label>
            <label>Sort by<select name="sort"><option value="name">Name A–Z</option><option value="recent" @selected(($filters['sort'] ?? '') === 'recent')>Recently published</option></select></label>
            <button class="button">Apply filters</button><a href="{{ $action }}">Clear filters</a>
        </form>
    </aside>
    <section aria-labelledby="results">
        <div class="result-heading">
            <h2 id="results">{{ $listings->total() }} {{ Str::plural('institution', $listings->total()) }} found</h2>
            <nav class="listing-actions" aria-label="Results layout">
                @foreach(['list' => 'List', 'grid' => 'Grid'] as $mode => $label)
                    <a class="button outline small" href="{{ request()->fullUrlWithQuery(['view' => $mode]) }}"
                        @if(($filters['view'] ?? 'list') === $mode) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </div>
        <div @class(['results', 'card-grid' => ($filters['view'] ?? '') === 'grid'])>
            @forelse($listings as $listing) @include('directory.card')
            @empty <p class="empty">No published profiles match your search. Try a different city or keyword.</p> @endforelse
        </div>
        @if($category && ($filters['city'] ?? '') && $areas->isNotEmpty())
            <div class="locality neighbourhood-links"><h2>Browse this city by neighbourhood</h2>
                <div class="area-grid">@foreach($areas as $area)
                    <a href="{{ route('directory.category', [$category, $filters['city'], $area->slug]) }}">{{ $area->name }}</a>
                @endforeach</div>
            </div>
        @endif
        @if($listings->hasPages())<nav class="pagination" aria-label="Results pages">
            @if($listings->previousPageUrl())<a class="button outline" href="{{ $listings->previousPageUrl() }}">← Previous</a>@endif
            <span>Page {{ $listings->currentPage() }} of {{ $listings->lastPage() }}</span>
            @if($listings->nextPageUrl())<a class="button outline" href="{{ $listings->nextPageUrl() }}">Next →</a>@endif
        </nav>@endif
    </section>
</div>
@endsection
