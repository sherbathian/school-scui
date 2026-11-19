@extends('directory.layout')
@section('content')
<section class="hero home-hero">
    <div class="wrap">
        <p class="eyebrow">Education directory for Islamabad &amp; Rawalpindi</p>
        <h1>Find the right school,<br> <em>college or university.</em></h1>
        <p class="intro">Explore verified institutions near you. Save your favourites and compare your shortlist side by side.</p>
        @include('directory.search', ['action' => route('directory.index')])
        @if($cities->isNotEmpty())<div class="chips"><span>Browse by city</span>
            @foreach($cities as $city)<a href="{{ route('directory.index', ['city' => $city->slug]) }}">{{ $city->name }}</a>@endforeach
        </div>@endif
    </div>
</section>
<div class="wrap">
    <nav class="categories" aria-label="Institution categories">
        @foreach (\App\Http\Controllers\PublicDirectoryController::TYPES as $path => [, $label])
            <a href="{{ route('directory.category', $path) }}"><span class="category-mark" aria-hidden="true">{{ ['schools' => '▤', 'colleges' => '▦', 'universities' => '▥', 'institutes' => '◇'][$path] }}</span><strong>{{ $label }}</strong><span aria-hidden="true">↗</span></a>
        @endforeach
    </nav>
    <section class="block">
        <div class="heading"><div><p class="eyebrow">The directory</p><h2>Recently published profiles</h2></div><a class="button outline" href="{{ route('directory.index') }}">Explore the directory →</a></div>
        <div class="card-grid">
            @forelse($listings as $listing) @include('directory.card')
            @empty <p class="empty">Verified profiles will appear here as they are published.</p> @endforelse
        </div>
    </section>
    @if($areas->isNotEmpty())
        <section class="block locality"><p class="eyebrow">Find a campus near you</p><h2>Browse by neighbourhood</h2>
            <div class="area-grid">@foreach($areas as $area)
                <a href="{{ route('directory.index', ['city' => $area->city_slug, 'area' => $area->slug]) }}"><strong>{{ $area->name }}</strong><span>{{ $area->city_name }}</span></a>
            @endforeach</div>
        </section>
    @endif
    <section class="institution"><h2>Represent an educational institution?</h2><a class="button teal" href="{{ route('directory.contact') }}">Contact TaleemFinder →</a></section>
</div>
@endsection
