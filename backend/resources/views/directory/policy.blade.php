@extends('directory.layout')
@section('title', $title)
@section('content')
<section class="hero directory-hero"><div class="wrap"><h1>{{ $title }}</h1></div></section>
<div class="wrap block"><article class="panel resource-detail">
    @foreach($paragraphs as $line)<p>{{ $line }}</p>@endforeach
    <a class="button outline" href="{{ route('directory.contact') }}">Contact support</a>
</article></div>
@endsection
