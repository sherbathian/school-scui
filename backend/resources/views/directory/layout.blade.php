<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Find schools, colleges and universities') · TaleemFinder</title>
    <meta name="description" content="@yield('description', 'Find and compare verified educational institutions in Islamabad and Rawalpindi.')">
    @yield('robots')
    <link rel="stylesheet" href="{{ asset('taleemfinder.css') }}">
</head>
<body>
<a class="skip" href="#content">Skip to content</a>
<div class="utility"><div class="wrap utility-inner"><span>● &nbsp; Islamabad &amp; Rawalpindi</span><a href="{{ route('directory.contact') }}">Contact support ↗</a></div></div>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="{{ route('directory.home') }}"><span class="brand-mark" aria-hidden="true">TF</span><span>Taleem<b>Finder</b></span></a>
        <nav aria-label="Main navigation">
            @foreach (\App\Http\Controllers\PublicDirectoryController::TYPES as $path => [, $label])
                <a href="{{ route('directory.category', $path) }}">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="header-actions"><a href="{{ route('directory.collection', 'saved') }}">Saved</a><a class="button outline" href="{{ route('directory.collection', 'compare') }}">Compare</a></div>
    </div>
</header>
<main id="content">
    @if(session('directory_message'))
        <p class="wrap notice" role="status">{{ session('directory_message') }}</p>
    @endif
    @yield('content')
</main>
<footer class="site-footer">
    <div class="wrap footer-grid">
        <div><a class="brand" href="{{ route('directory.home') }}"><span class="brand-mark" aria-hidden="true">TF</span><span>Taleem<b>Finder</b></span></a>
            <p>Discover and compare education options across Islamabad and Rawalpindi.</p></div>
        <div><h2>Directories</h2>@foreach (\App\Http\Controllers\PublicDirectoryController::TYPES as $path => [, $label])
            <a href="{{ route('directory.category', $path) }}">{{ $label }}</a>@endforeach</div>
        <div><h2>Resources</h2><a href="{{ route('directory.collection', 'compare') }}">Compare institutions</a><a href="{{ route('directory.collection', 'saved') }}">Saved listings</a></div>
        <div><h2>TaleemFinder</h2><a href="{{ route('directory.about') }}">About us</a><a href="{{ route('directory.contact') }}">Contact support</a></div>
    </div>
    <div class="wrap footer-bottom">© {{ now()->year }} TaleemFinder</div>
</footer>
</body>
</html>
