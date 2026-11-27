@php($children = $node['content'] ?? [])
@switch($node['type'])
    @case('doc')
        @each('school-pages.node', $children, 'node')
        @break
    @case('paragraph')
        <p>{{ \App\Services\SchoolPageDocument::inline($children) }}</p>
        @break
    @case('heading')
        @if($node['attrs']['level'] === 2)
            <h2>{{ \App\Services\SchoolPageDocument::inline($children) }}</h2>
        @else
            <h3>{{ \App\Services\SchoolPageDocument::inline($children) }}</h3>
        @endif
        @break
    @case('bulletList')
        <ul>@each('school-pages.node', $children, 'node')</ul>
        @break
    @case('orderedList')
        <ol start="{{ $node['attrs']['start'] ?? 1 }}">@each('school-pages.node', $children, 'node')</ol>
        @break
    @case('listItem')
        <li>@each('school-pages.node', $children, 'node')</li>
        @break
@endswitch
