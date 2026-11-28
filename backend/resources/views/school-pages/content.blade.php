@if(\App\Services\SchoolPageDocument::valid($document ?? null))
    @include('school-pages.node', ['node' => $document])
@else
    @foreach($blocks as $block)
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
                        @foreach($block['items'] as $item)
                            @if(is_string($item)) <li>{{ $item }}</li> @endif
                        @endforeach
                    </ul>
                @endif
                @break
            @case('link')
                @php($url = $block['url'] ?? null)
                @if(is_string($url) && preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL)
                    && is_string($block['text'] ?? null))
                    <p><a href="{{ $url }}">{{ $block['text'] }}</a></p>
                @endif
                @break
        @endswitch
    @endforeach
@endif
