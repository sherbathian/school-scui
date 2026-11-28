@php
    $home = $page->slug === 'home';
    $admissions = $navigation->firstWhere('slug', 'admissions');
    $photos = $photos ?? collect();
    $hero = $photos->first();
    $mediaUrl = fn ($photo) => (isset($siteSubdomain) ? 'https://taleemfinder.pk' : '')
        . route('directory.media', [$branch->slug, $photo->id], absolute: false);
    $quickLinks = $navigation->whereIn('slug', ['about', 'academics', 'admissions', 'gallery', 'activities']);
@endphp
<a class="ifs-skip" href="#school-content">Skip to content</a>
<header class="ifs-header">
    <div class="wrap masthead">
        <a class="ifs-brand" href="{{ $pageUrl() }}">
            <span class="ifs-monogram" aria-hidden="true">IFS</span>
            <span>{{ $branch->name }}</span>
        </a>
        @if($navigation->isNotEmpty())
            <nav aria-label="School pages">
                @foreach($navigation as $item)
                    <a href="{{ $pageUrl($item->slug) }}" @if($item->slug === $page->slug) aria-current="page" @endif>{{ $item->title }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</header>
<main id="school-content" class="ifs-main">
    @if($home)
        <section class="ifs-hero">
            <div class="wrap ifs-hero-grid">
                <div>
                    <p class="ifs-kicker">Welcome to</p>
                    <h1>{{ $branch->name }}</h1>
                    <div class="ifs-actions">
                        @if($admissions)<a class="ifs-button" href="{{ $pageUrl($admissions->slug) }}">Admissions <span aria-hidden="true">↗</span></a>@endif
                        <a class="ifs-secondary" href="#school-introduction">Explore our school <span aria-hidden="true">↓</span></a>
                    </div>
                </div>
                @if($hero)
                    <figure class="ifs-hero-photo">
                        <img src="{{ $mediaUrl($hero) }}" alt="{{ $hero->alt_text ?: $hero->caption ?: 'School campus' }}" fetchpriority="high">
                    </figure>
                @elseif(app()->environment('local') && is_file(public_path('school-themes/ifs-classroom.webp')))
                    <figure class="ifs-hero-photo">
                        <img src="{{ asset('school-themes/ifs-classroom.webp') }}" alt="Illustrative classroom scene" fetchpriority="high">
                        <figcaption>Illustrative preview image</figcaption>
                    </figure>
                @else
                    <div class="ifs-hero-mark" aria-hidden="true"><span>IFS</span></div>
                @endif
            </div>
        </section>
        @if($quickLinks->isNotEmpty())
            <div class="wrap ifs-quick-links">
                @foreach($quickLinks as $item)
                    <a class="ifs-card" href="{{ $pageUrl($item->slug) }}">{{ $item->title }} <span aria-hidden="true">↗</span></a>
                @endforeach
            </div>
        @endif
        <article id="school-introduction" class="wrap ifs-copy">
            @include('school-pages.content')
        </article>
        @if($photos->count() > 1)
            <section class="wrap ifs-gallery">
                <h2>Campus life</h2>
                <div class="ifs-gallery-grid">
                    @foreach($photos->slice(1) as $photo)
                        <figure>
                            <img src="{{ $mediaUrl($photo) }}" alt="{{ $photo->alt_text ?: $photo->caption ?: 'School campus' }}" loading="lazy">
                            @if($photo->caption)<figcaption>{{ $photo->caption }}</figcaption>@endif
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif
        @if($admissions)
            <section class="wrap ifs-admissions">
                <h2>Planning your next school year?</h2>
                <a class="ifs-button" href="{{ $pageUrl($admissions->slug) }}">View admissions information <span aria-hidden="true">↗</span></a>
            </section>
        @endif
    @else
        <div class="ifs-inner">
            <div class="wrap">
                <a href="{{ $pageUrl() }}">Home</a>
                <h1>{{ $page->title }}</h1>
            </div>
        </div>
        <article class="wrap ifs-copy">
            @include('school-pages.content')
        </article>
    @endif
</main>
<footer class="ifs-footer">
    <div class="wrap">
        <a href="{{ $pageUrl() }}">{{ $branch->name }}</a>
        @if($site->footer_text)<p>{{ $site->footer_text }}</p>@endif
    </div>
</footer>
