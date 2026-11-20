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
    <form class="block" method="post" action="{{ route('institute.logout') }}">@csrf<button class="button outline">Sign out</button></form>
</div>
@endsection
