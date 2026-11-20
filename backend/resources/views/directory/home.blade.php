@extends('directory.layout')
@section('utility')
<div class="home-utility"><div class="wrap">
    <span>Islamabad &amp; Rawalpindi</span>
    <a href="{{ route('directory.for-schools') }}">For institutions ↗</a>
</div></div>
@endsection
@section('content')
<section class="hero home-hero">
    <div class="wrap">
        <p class="home-region">Education discovery in Islamabad &amp; Rawalpindi</p>
        <h1>Find the Right School, College &amp; Academic Path with Confidence</h1>
        <p class="intro">Explore campuses across Islamabad and Rawalpindi by sector, curriculum, facilities, and admission windows.</p>
        @include('directory.search', ['action' => route('directory.index'), 'homeSearch' => true])
        <div class="chips"><span>Popular searches</span>
            @foreach($cities->whereIn('slug', ['islamabad', 'rawalpindi']) as $city)
                <a href="{{ route('directory.index', ['city' => $city->slug]) }}">{{ $city->name }} campuses</a>
            @endforeach
            @foreach($boards->filter(fn ($board) => str($board->name)->lower()->contains(['cambridge', 'federal', 'fbise']))->take(2) as $board)
                <a href="{{ route('directory.index', ['board' => $board->slug]) }}">{{ $board->name }}</a>
            @endforeach
            <a class="admissions-chip" href="{{ route('directory.resources', 'admissions') }}">Admissions Tracker</a>
        </div>
    </div>
</section>
<div class="wrap">
    <nav class="categories" aria-label="Institution categories">
        @foreach ([
            ['Schools', 'Montessori to A-Level', 'school', route('directory.category', 'schools')],
            ['Colleges', 'FSc, ICS & Entry Prep', 'college', route('directory.category', 'colleges')],
            ['Universities', 'BS, MS & Scholarships', 'university', route('directory.category', 'universities')],
            ['Institutes', 'IT, Languages & Skills', 'chip', route('directory.category', 'institutes')],
            ['Courses', 'Online & Skills Training', 'play', route('directory.resources', 'courses')],
            ['Book Exchange', 'Buy, Swap & Donate Books', 'book', route('directory.resources', 'books')],
        ] as [$label, $description, $icon, $url])
            <a href="{{ $url }}"><span class="category-mark" data-icon="{{ $icon }}">@include('directory.icon', ['icon' => $icon])</span>
                <strong>{{ $label }}</strong><small>{{ $description }}</small></a>
        @endforeach
    </nav>
    <section class="block home-featured">
        <div class="heading"><div><p class="eyebrow">The directory</p><h2>Featured campus profiles</h2><p>Explore curriculum, education levels, and published fees before you shortlist.</p></div><a class="button outline" href="{{ route('directory.index') }}">Explore the directory →</a></div>
        <div class="card-grid">
            @forelse($listings as $listing) @include('directory.card', ['featuredCard' => true, 'campusFacilities' => $facilities->get($listing->id, collect())])
            @empty <p class="empty">Verified profiles will appear here as they are published.</p> @endforelse
        </div>
    </section>
    @if($areas->isNotEmpty())
        <section class="block locality home-locality"><p class="eyebrow">Find a campus near you</p><h2>Browse by sector and locality</h2>
            <div class="area-grid">@foreach($areas as $area)
                <a href="{{ route('directory.index', ['city' => $area->city_slug, 'area' => $area->slug]) }}"><strong>{{ $area->name }}</strong><span>{{ $area->city_name }}</span></a>
            @endforeach</div>
        </section>
    @endif
    <section class="block home-guide">
        <h2>How TaleemFinder simplifies admissions</h2>
        <div class="card-grid">
            @foreach ([
                ['search', 'Discover and filter locally', 'Search by neighbourhood and curriculum to find institutions near you.', route('directory.index')],
                ['compare', 'Compare side by side', 'Shortlist up to three institutions and review their profiles together.', route('directory.collection', 'compare')],
                ['book', 'Track admissions', 'Browse programmes accepting enquiries and find upcoming education events.', route('directory.resources', 'admissions')],
            ] as [$icon, $title, $copy, $url])
                <a class="panel home-guide-card" href="{{ $url }}"><span class="category-mark">@include('directory.icon', ['icon' => $icon])</span>
                    <h3>{{ $title }}</h3><p>{{ $copy }}</p></a>
            @endforeach
        </div>
    </section>
    <section class="institution home-institution">
        <div><h2>Represent an educational institution in Pakistan?</h2>
            <p>Add your campus or claim an existing listing so families can find current programmes and admissions information.</p></div>
        <div class="home-institution-actions">
            <a class="button teal" href="{{ route('directory.for-schools') }}">Add or claim your institution</a>
            <a class="button outline" href="{{ route('directory.resources', 'parent-guides') }}">Read parent guides</a>
        </div>
    </section>
</div>
@if($count = count(session('directory.compare', [])))
    <aside class="home-compare-tray" aria-label="Comparison shortlist">
        <span>@include('directory.icon', ['icon' => 'compare']) {{ $count }} of 3 selected</span>
        <a class="button teal small" href="{{ route('directory.collection', 'compare') }}">Compare now →</a>
    </aside>
@endif
@endsection
