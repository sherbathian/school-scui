@extends('directory.layout')
@section('title', 'For schools')
@section('content')
<section class="hero directory-hero"><div class="wrap">
    <h1>For schools and institutes</h1>
    <p class="intro">Give families a clear view of your campus, programmes and admissions.</p>
</div></section>
<section class="wrap block"><div class="card-grid">
    <article class="panel public-resource-card"><h2>Add an institution</h2><p>Submit your campus details for review.</p><a class="button teal" href="{{ route(auth()->check() ? 'institute.new' : 'institute.register') }}">Add your school</a></article>
    <article class="panel public-resource-card"><h2>Claim a listing</h2><p>Find your campus, then use the claim link on its profile.</p><a class="button outline" href="{{ route('directory.index') }}">Find your campus</a></article>
    <article class="panel public-resource-card"><h2>Update your information</h2><p>Contact support if you need to correct published information.</p><a class="button outline" href="{{ route('directory.contact') }}">Contact support</a></article>
</div></section>
@endsection
