<article class="listing">
    <div class="initial" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($listing->short_name ?: $listing->name, 0, 2)) }}</div>
    <div class="listing-details">
        <span class="tag verified">✓ Verified</span>
        <h3><a href="{{ route('directory.profile', $listing->slug) }}">{{ $listing->name }}</a></h3>
        @if($listing->area_name || $listing->city_name)
            <p>{{ implode(' · ', array_filter([$listing->area_name, $listing->city_name])) }}</p>
        @endif
        @if($listing->tagline)<p>{{ $listing->tagline }}</p>@endif
    </div>
    <div class="listing-actions">
        @foreach(['saved' => 'Save', 'compare' => 'Compare'] as $key => $label)
            @php($selected = in_array($listing->slug, $key === 'saved' ? ($savedSlugs ?? []) : session('directory.compare', []), true))
            <form method="post" action="{{ route('directory.toggle', [$key, $listing->slug]) }}">
                @csrf <button class="button outline small" aria-pressed="{{ $selected ? 'true' : 'false' }}" aria-label="{{ $label }} {{ $listing->name }}">{{ $selected ? ($key === 'saved' ? 'Saved' : 'Selected') : $label }}</button>
            </form>
        @endforeach
        <a class="button small" href="{{ route('directory.profile', $listing->slug) }}">View profile <span aria-hidden="true">↗</span></a>
    </div>
</article>
