<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Find schools, colleges and universities') · TaleemFinder</title>
    <meta name="description" content="@yield('description', 'Find and compare verified educational institutions in Islamabad and Rawalpindi.')">
    @yield('robots')
    <link rel="stylesheet" href="{{ asset('taleemfinder.css') }}?v={{ filemtime(public_path('taleemfinder.css')) }}">
</head>
<body>
<a class="skip" href="#content">Skip to content</a>
@yield('utility')
<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="{{ route('directory.home') }}">
            <span class="brand-mark">@include('directory.icon', ['icon' => 'cap'])</span>
            <span>Taleem<b>Finder</b><small>taleemfinder.pk</small></span>
        </a>
        <nav aria-label="Main navigation">
            <details class="public-nav-menu"><summary>Find Schools</summary><div>
                <a href="{{ route('directory.category', 'schools') }}">All schools</a>
                <a href="{{ route('directory.category', ['schools', 'islamabad']) }}">Schools in Islamabad</a>
                <a href="{{ route('directory.category', ['schools', 'rawalpindi']) }}">Schools in Rawalpindi</a>
            </div></details>
            <details class="public-nav-menu"><summary>Higher Ed &amp; Skills</summary><div>
                @foreach (array_slice(\App\Http\Controllers\PublicDirectoryController::TYPES, 1, null, true) as $path => [, $label])
                    <a href="{{ route('directory.category', $path) }}">{{ $label }}</a>
                @endforeach
                <a href="{{ route('directory.resources', 'courses') }}">Courses</a>
            </div></details>
            <a class="header-admissions" href="{{ route('directory.resources', 'admissions') }}">Admissions</a>
            <details class="public-nav-menu"><summary>Community</summary><div>
                @foreach (array_diff_key(\App\Http\Controllers\PublicResourcesController::PAGES, array_flip(['courses', 'admissions'])) as $key => [$label])
                    <a href="{{ route('directory.resources', $key) }}">{{ $label }}</a>
                @endforeach
            </div></details>
        </nav>
        <div class="header-actions">
            <a class="header-compare" href="{{ route('directory.collection', 'compare') }}">@include('directory.icon', ['icon' => 'compare']) Compare <span>{{ count(session('directory.compare', [])) }}</span></a>
            <a class="header-saved" aria-label="Saved listings" title="Saved listings" href="{{ route('directory.collection', 'saved') }}">@include('directory.icon', ['icon' => 'bookmark'])</a>
            <a class="button header-add" href="{{ route('directory.for-schools') }}">@include('directory.icon', ['icon' => 'plus']) Add Your School</a>
            @auth
                <a class="button header-account" href="{{ route('directory.dashboard') }}">My account</a>
            @else
                <a href="{{ route('directory.sign-in') }}">Sign In</a>
                <a class="button header-account" href="{{ route('directory.sign-up') }}">Sign Up</a>
            @endauth
        </div>
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
        <div><h2>Resources</h2>
            @foreach(\App\Http\Controllers\PublicResourcesController::PAGES as $key => [$label])
                <a href="{{ route('directory.resources', $key) }}">{{ $label }}</a>
            @endforeach<a href="{{ route('directory.collection', 'compare') }}">Compare institutions</a><a href="{{ route('directory.collection', 'saved') }}">Saved listings</a></div>
        <div><h2>TaleemFinder</h2><a href="{{ route('directory.about') }}">About us</a><a href="{{ route('directory.contact') }}">Contact support</a><a href="{{ route(auth()->check() ? 'institute.new' : 'institute.register') }}">Institute registration</a>
            <a href="{{ route('directory.for-schools') }}">For schools</a>
            @foreach(['community-privacy' => 'Community privacy', 'terms-of-use' => 'Terms of use', 'copyright-takedown' => 'Copyright and takedown'] as $topic => $label)
                <a href="{{ route('directory.policy', $topic) }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
    <div class="wrap footer-bottom">© {{ now()->year }} TaleemFinder</div>
</footer>
</body>
</html>
