@php
    $logo = $listing->logo_path;
    $logoUrl = is_string($logo) && preg_match('~\A(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_.-]+\.(?:png|jpe?g|webp|gif|avif)\z~i', $logo)
        && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)
        ? route('directory.logo', $listing->slug) : null;
@endphp
<article class="listing{{ ($featuredCard ?? false) ? ' featured-listing' : '' }}">
    <div class="initial" aria-hidden="true">@if($logoUrl)<img src="{{ $logoUrl }}" alt="" loading="lazy" width="48" height="48">@else{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($listing->short_name ?: $listing->name, 0, 2)) }}@endif</div>
    <div class="listing-details">
        <span class="tag verified">✓ Verified</span>
        <h3><a href="{{ route('directory.profile', $listing->slug) }}">{{ $listing->name }}</a></h3>
        @if($listing->area_name || $listing->city_name)
            <p>{{ implode(' · ', array_filter([$listing->area_name, $listing->city_name])) }}</p>
        @endif
        @if($listing->tagline)<p>{{ $listing->tagline }}</p>@endif
    </div>
    @if($featuredCard ?? false)
        <div class="featured-facts">
            <dl><div><dt>Curriculum</dt><dd>{{ $listing->curriculum ?: 'Contact campus' }}</dd></div>
                <div><dt>Education level</dt><dd>{{ $listing->education_level ?: 'Contact campus' }}</dd></div></dl>
            <p>{{ $listing->monthly_fee !== null ? 'Monthly fee · '.$listing->fee_currency.' '.number_format($listing->monthly_fee, 2) : 'Monthly fee available on enquiry' }}</p>
            @if($campusFacilities->isNotEmpty())
                <ul aria-label="Facilities">@foreach($campusFacilities->take(3) as $facility)<li>{{ $facility->name }}</li>@endforeach</ul>
            @endif
        </div>
    @endif
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
