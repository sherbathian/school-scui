@php
    $isIfs = ($site->theme_slug ?? null) === 'ifs';
    $appearance = \App\Services\SchoolPageDocument::appearance($site);
    $pageUrl = fn (string $slug = 'home') => isset($siteSubdomain)
        ? route('school-site.host', ['subdomain' => $siteSubdomain, 'page' => $slug === 'home' ? null : $slug], absolute: false)
        : route('school-site.page', ['school' => $branch->slug, 'page' => $slug === 'home' ? null : $slug]);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->seo_title ?: $page->title }} · {{ $branch->name }}</title>
    @if($page->seo_description)
        <meta name="description" content="{{ $page->seo_description }}">
    @endif
    <style>
        :root { color-scheme: light; font: 100%/1.6 "{{ $appearance['font'] }}", system-ui, sans-serif; color: {{ $appearance['primary'] }}; background: #f7faf8; }
        body { margin: 0; }
        a { color: {{ $appearance['secondary'] }}; text-underline-offset: .2em; }
        a:focus-visible { outline: 2px solid currentColor; outline-offset: 4px; }
        .wrap { width: min(68rem, calc(100% - 2rem)); margin-inline: auto; }
        header { background: #fff; border-bottom: 1px solid #dce6e1; }
        .masthead { display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap; gap: .75rem 2rem; padding-block: 1.25rem; }
        .brand { font-weight: 750; font-size: 1.15rem; }
        nav { display: flex; flex-wrap: wrap; gap: .5rem 1rem; }
        nav [aria-current] { font-weight: 700; }
        main { padding-block: clamp(2rem, 7vw, 5rem); }
        article { max-width: 46rem; overflow-wrap: anywhere; }
        h1 { font-size: clamp(2rem, 6vw, 3.5rem); line-height: 1.1; margin: 0 0 1.5rem; }
        h2 { line-height: 1.25; }
        footer { border-top: 1px solid #dce6e1; padding-block: 1.25rem; overflow-wrap: anywhere; }
    </style>
    @if($isIfs)
        @include('school-pages.ifs-styles')
    @endif
</head>
<body @class(['ifs' => $isIfs])>
@if($isIfs)
    @include('school-pages.ifs')
@else
<header>
    <div class="wrap masthead">
        <a class="brand" href="{{ $pageUrl() }}">{{ $branch->name }}</a>
        @if($navigation->isNotEmpty())
            <nav aria-label="School pages">
                @foreach($navigation as $item)
                    <a href="{{ $pageUrl($item->slug) }}"
                        @if($item->slug === $page->slug) aria-current="page" @endif>{{ $item->title }}</a>
                @endforeach
            </nav>
        @endif
    </div>
</header>
<main class="wrap">
    <article>
        <h1>{{ $page->title }}</h1>
        @include('school-pages.content')
    </article>
</main>
@if($site->footer_text)
    <footer><div class="wrap">{{ $site->footer_text }}</div></footer>
@endif
@endif
</body>
</html>
