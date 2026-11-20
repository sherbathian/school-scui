<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }} · {{ $branch->name }} preview</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; color: #162b27; background: #f4f7f6; }
        body { margin: 0; }
        .shell { width: min(73rem, calc(100% - 2rem)); margin-inline: auto; }
        .preview-bar { color: #e8f5ef; background: #153a32; padding: .65rem 0; font-size: .85rem; }
        header { background: #fff; border-bottom: 1px solid #dce6e1; }
        .header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding-block: 1rem; }
        .brand { font-weight: 750; font-size: 1.25rem; }
        nav { display: flex; flex-wrap: wrap; gap: .3rem; }
        a { color: #145b44; text-underline-offset: .2em; }
        nav a { border-radius: .5rem; padding: .45rem .7rem; text-decoration: none; }
        nav a:is(:hover, :focus-visible, [aria-current]) { background: #e6f2ed; }
        main { padding-block: clamp(1.5rem, 5vw, 4rem); }
        article { max-width: 48rem; border: 1px solid #dce6e1; border-radius: 1rem; background: #fff; padding: clamp(1.25rem, 4vw, 3.25rem); }
        h1 { font-size: clamp(2rem, 5vw, 3.5rem); line-height: 1.08; margin: 0 0 1.5rem; }
        h2 { font-size: 1.3rem; margin: 1.5em 0 .4em; }
        p, li { line-height: 1.65; }
        .back { display: inline-block; margin-top: 1.25rem; }
    </style>
</head>
<body>
<div class="preview-bar"><div class="shell">Local preview · {{ $page->is_published ? 'Published in editor' : 'Draft' }}</div></div>
<header>
    <div class="shell header">
        <span class="brand">{{ $branch->name }}</span>
        <nav aria-label="School pages">
            @foreach ($navigation as $item)
                <a href="{{ route('local.school-page-preview', ['school' => $branch->slug, 'page' => $item->slug]) }}"
                    @if ($item->slug === $page->slug) aria-current="page" @endif>{{ $item->title }}</a>
            @endforeach
        </nav>
    </div>
</header>
<main class="shell">
    <article>
        <h1>{{ $page->title }}</h1>
        @foreach ($blocks as $block)
            @continue(!is_array($block))
            @switch($block['type'] ?? null)
                @case('heading')
                    @if(is_string($block['text'] ?? null)) <h2>{{ $block['text'] }}</h2> @endif
                    @break
                @case('paragraph')
                    @if(is_string($block['text'] ?? null)) <p>{{ $block['text'] }}</p> @endif
                    @break
                @case('list')
                    @if(is_array($block['items'] ?? null))
                        <ul>
                            @foreach ($block['items'] as $item)
                                @if(is_string($item)) <li>{{ $item }}</li> @endif
                            @endforeach
                        </ul>
                    @endif
                    @break
            @endswitch
        @endforeach
        @if ($blocks === [])
            <p>This page has no content blocks yet.</p>
        @endif
    </article>
    <a class="back" href="/admin/services/school-pages?school_id={{ $branch->id }}">Back to school pages</a>
</main>
</body>
</html>
