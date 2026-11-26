@extends('directory.layout')
@section('title', 'My account')
@section('robots')<meta name="robots" content="noindex">@endsection
@section('content')
<section class="hero directory-hero"><div class="wrap"><h1>My account</h1><p class="intro">{{ auth()->user()->name }}</p></div></section>
<div class="wrap block">
    <div class="card-grid">
        <a class="panel home-resource-link" href="{{ route('directory.collection', 'saved') }}"><strong>Saved schools</strong><span>Revisit your shortlist.</span></a>
        <a class="panel home-resource-link" href="{{ route('directory.collection', 'compare') }}"><strong>Compare institutions</strong><span>Review your current comparison.</span></a>
        <a class="panel home-resource-link" href="{{ route('directory.resources', 'admissions') }}"><strong>Admissions</strong><span>Browse programmes accepting enquiries.</span></a>
    </div>
    <section class="block">
        <h2>My listings</h2>
        <ul>
            @forelse($listings as $listing)
                <li><a href="{{ route('institute.claim', $listing->slug) }}">{{ $listing->name }}</a></li>
            @empty
                <li>You have no listing submissions or claims yet.</li>
            @endforelse
        </ul>
        <a class="button teal" href="{{ route('institute.new') }}">Add an institution</a>
    </section>
    <form class="block" method="post" action="{{ route('institute.logout') }}">@csrf<button class="button outline">Sign out</button></form>
</div>
@endsection
