@extends('directory.layout')
@section('title', $listing->seo_title ?: $listing->name)
@section('description', $listing->seo_description ?: ($listing->tagline ?: 'View the verified TaleemFinder profile for '.$listing->name.'.'))
@section('content')
<section class="hero directory-hero"><div class="wrap">
    <p class="breadcrumb"><a href="{{ route('directory.home') }}">Home</a> / <a href="{{ route('directory.index') }}">Directory</a></p>
    <p class="eyebrow">Verified institution</p><h1>{{ $listing->name }}</h1>
    @if($listing->branch_name || $listing->city_name)<p class="intro">{{ implode(' · ', array_filter([$listing->branch_name, $listing->area_name, $listing->city_name])) }}</p>@endif
</div></section>
<div class="wrap profile-grid">
    <section class="panel"><h2>About this institution</h2>
        @if($listing->tagline)<p><strong>{{ $listing->tagline }}</strong></p>@endif
        @if($listing->established_year)<p>Established {{ $listing->established_year }}</p>@endif
        @if($listing->medium)<p>Language of instruction: {{ ['english' => 'English', 'urdu' => 'Urdu', 'both' => 'English and Urdu'][$listing->medium] }}</p>@endif
        <p class="description">{{ $listing->description ?: 'A detailed overview has not been published yet.' }}</p>
        <div class="listing-actions">
            <a class="button outline" href="{{ route('institute.claim', $listing->slug) }}">Claim this institution</a>
            @foreach(['saved' => 'Save listing', 'compare' => 'Add to comparison'] as $key => $label)
                @php($selected = in_array($listing->slug, $key === 'saved' ? ($savedSlugs ?? []) : session('directory.compare', []), true))
                <form method="post" action="{{ route('directory.toggle', [$key, $listing->slug]) }}">@csrf
                <button class="button outline">{{ $selected ? ($key === 'saved' ? 'Remove from saved' : 'Remove from comparison') : $label }}</button></form>
            @endforeach
        </div>
    </section>
    <aside class="panel"><h2>Contact &amp; location</h2>
        @if($listing->address_line)<p>{{ $listing->address_line }}</p>@endif
        @if($listing->phone)<p>{{ $listing->phone }}</p>@endif
        @if($listing->email)<p><a href="mailto:{{ $listing->email }}">{{ $listing->email }}</a></p>@endif
        @if($listing->website_url && preg_match('/^https?:\/\//i', $listing->website_url))
            <p><a href="{{ $listing->website_url }}" rel="noopener noreferrer">Visit institution website ↗</a></p>
        @endif
        @unless($listing->address_line || $listing->phone || $listing->email || $listing->website_url)<p>No public contact details have been published.</p>@endunless
    </aside>
</div>
@endsection
