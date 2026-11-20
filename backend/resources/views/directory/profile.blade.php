@extends('directory.layout')
@section('title', $listing->seo_title ?: $listing->name)
@section('description', $listing->seo_description ?: ($listing->tagline ?: 'Discover '.$listing->name.' on TaleemFinder.'))
@section('content')
<section class="hero directory-hero profile-hero"><div class="wrap">
    @php
        $category = collect(\App\Http\Controllers\PublicDirectoryController::TYPES)
            ->search(fn ($type) => $type[0] === $listing->institute_type);
        $categoryLabel = \App\Http\Controllers\PublicDirectoryController::CATEGORY_LABELS[$category] ?? 'Institution';
        $location = implode(' · ', array_unique(array_filter([$listing->branch_name, $listing->area_name, $listing->city_name])));
    @endphp
    <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('directory.home') }}">Home</a> / <a href="{{ $category === false ? route('directory.index') : route('directory.category', $category) }}">{{ $categoryLabel }}</a> / <span aria-current="page">{{ $listing->name }}</span></nav>
    <div class="profile-identity">
        <span class="profile-monogram" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::take($listing->name, 2)) }}</span>
        <div>
            <p class="eyebrow">Verified {{ \Illuminate\Support\Str::lower($categoryLabel) }}</p>
            <h1>{{ $listing->name }}</h1>
            @if($location)<p class="intro">{{ $location }}</p>@endif
        </div>
    </div>
    <div class="profile-hero-actions">
        <a class="button teal" href="#enquiry">Admission enquiry</a>
        @foreach(['saved' => 'Save listing', 'compare' => 'Compare'] as $key => $label)
            @php($selected = in_array($listing->slug, $key === 'saved' ? ($savedSlugs ?? []) : session('directory.compare', []), true))
            <form method="post" action="{{ route('directory.toggle', [$key, $listing->slug]) }}">@csrf
                <button class="button outline" aria-pressed="{{ $selected ? 'true' : 'false' }}">{{ $selected ? ($key === 'saved' ? 'Remove from saved' : 'Remove from comparison') : $label }}</button>
            </form>
        @endforeach
    </div>
</div></section>
<div class="profile-section-bar">
    <nav class="wrap profile-sections" aria-label="Profile sections">
        <a href="#overview">Overview</a>
        @if($programs->isNotEmpty())<a href="#programmes">Programmes</a>@endif
        <a href="#fees">Fees</a>
        <a href="#gallery">Gallery</a>
        <a href="#reviews">Reviews</a>
        <a href="#location">Location</a>
        <a href="#enquiry">Enquire</a>
    </nav>
</div>
<div class="wrap profile-grid">
    <div class="profile-stack">
        <section class="panel" id="overview"><h2>About this institution</h2>
            @if($listing->tagline)<p><strong>{{ $listing->tagline }}</strong></p>@endif
            @if($listing->established_year)<p>Established {{ $listing->established_year }}</p>@endif
            @if($listing->medium)<p>Language of instruction: {{ ['english' => 'English', 'urdu' => 'Urdu', 'both' => 'English and Urdu'][$listing->medium] ?? $listing->medium }}</p>@endif
            <p class="description">{{ $listing->description ?: 'A detailed overview has not been published yet.' }}</p>
            @foreach(['boards'=>'Curriculum', 'education_levels'=>'Education levels', 'facilities'=>'Facilities'] as $key => $label)
                @if($tags[$key]->isNotEmpty())<div class="profile-tags"><h3>{{ $label }}</h3><ul>@foreach($tags[$key] as $tag)<li>{{ $tag }}</li>@endforeach</ul></div>@endif
            @endforeach
        </section>
        @if($programs->isNotEmpty())
            <section class="panel" id="programmes"><h2>Programmes</h2><ul class="profile-records">@foreach($programs as $program)
                <li><strong>{{ $program->title }}</strong> @if($program->admission_open) · <a href="{{ route('directory.resource', ['admissions', $program->id]) }}">Admissions open</a>@endif</li>
            @endforeach</ul></section>
        @endif
        <section class="panel" id="fees"><h2>Published fees</h2>
            @forelse($fees as $fee)
                <div class="profile-record"><strong>{{ $fee->label ?: ($fee->level_name ?: 'Fee information') }}</strong>
                    <dl class="profile-fees">
                        @foreach(['monthly_fee' => 'Monthly fee', 'admission_fee' => 'Admission fee', 'annual_charges' => 'Annual charges'] as $field => $label)
                            @if($fee->{$field} !== null)
                                <div><dt>{{ $label }}</dt><dd>{{ $fee->currency }} {{ number_format($fee->{$field}) }}</dd></div>
                            @endif
                        @endforeach
                    </dl>
                    @if($fee->last_confirmed_at)<small>Last confirmed {{ \Illuminate\Support\Carbon::parse($fee->last_confirmed_at)->format('j M Y') }}</small>@endif
                    @if($fee->notes)<p>{{ $fee->notes }}</p>@endif
                </div>
            @empty<p>Contact the institution for current fee information.</p>@endforelse
        </section>
        <section class="panel" id="gallery"><h2>Campus gallery</h2>
            @forelse($media as $asset)
                @if($asset->path && preg_match('~\Alisting-media/[A-Za-z0-9]+\.(?:jpg|jpeg|png|webp|pdf)\z~', $asset->path))
                    @if($asset->kind === 'photo' && !str_ends_with($asset->path, '.pdf'))
                        <figure class="profile-photo"><img loading="lazy" src="{{ route('directory.media', [$listing->slug, $asset->id]) }}" alt="{{ $asset->alt_text ?: 'Campus photo' }}">@if($asset->caption)<figcaption>{{ $asset->caption }}</figcaption>@endif</figure>
                    @else
                        <p><a href="{{ route('directory.media', [$listing->slug, $asset->id]) }}">{{ $asset->caption ?: 'View brochure' }} ↗</a></p>
                    @endif
                @elseif($asset->url && preg_match('/^https?:\/\//i', $asset->url))
                    <p><a href="{{ $asset->url }}" rel="noopener noreferrer">{{ $asset->caption ?: 'Open '.$asset->kind }} ↗</a></p>
                @endif
            @empty<p>No media has been published for this campus.</p>@endforelse
        </section>
        <section class="panel" id="reviews"><h2>Reviews</h2>
            @forelse($reviews as $review)<article class="profile-record"><p class="resource-meta">{{ $review->rating }}/5 · {{ ucfirst($review->relationship) }}</p>
                @if($review->title)<h3>{{ $review->title }}</h3>@endif
                <p>{{ $review->body }}</p>
                @if($review->school_reply)<p><strong>School reply</strong> {{ $review->school_reply }}</p>@endif
            </article>@empty<p>No approved reviews yet.</p>@endforelse
        </section>
    </div>
    <aside class="profile-stack">
        <section class="panel" id="location"><h2>Contact &amp; location</h2>
            @if($listing->address_line)<p>{{ $listing->address_line }}</p>@endif
            @if($listing->phone)<p>{{ $listing->phone }}</p>@endif
            @if($listing->email)<p><a href="mailto:{{ $listing->email }}">{{ $listing->email }}</a></p>@endif
            @if($listing->website_url && preg_match('/^https?:\/\//i', $listing->website_url))
                <p><a href="{{ $listing->website_url }}" rel="noopener noreferrer">Visit institution website ↗</a></p>
            @endif
            @unless($listing->address_line || $listing->phone || $listing->email || $listing->website_url)<p>No public contact details have been published.</p>@endunless
            @if($listing->address_line)<a class="button outline" href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($listing->name.' '.$listing->address_line) }}" rel="noopener noreferrer">Open map ↗</a>@endif
        </section>
        <section class="panel profile-enquiry" id="enquiry"><h2>Interested in admission?</h2>
            <p>Send a question directly to this institution.</p>
            @if($errors->any())<p class="profile-error" role="alert">{{ $errors->first() }}</p>@endif
            <form class="resource-enquiry" method="post" action="{{ route('directory.enquire', $listing->slug) }}">@csrf
                <label>Your name<input name="parent_name" required maxlength="150" value="{{ old('parent_name') }}"></label>
                <label>Phone<input name="phone" type="tel" required maxlength="30" value="{{ old('phone') }}"></label>
                <label>Email (optional)<input name="email" type="email" maxlength="190" value="{{ old('email') }}"></label>
                <label>Grade or programme<input name="grade_interested" maxlength="60" value="{{ old('grade_interested') }}"></label>
                <label>Message<textarea name="message" rows="3" required maxlength="2000">{{ old('message') }}</textarea></label>
                <label class="enquiry-consent"><input type="checkbox" name="consent_given" value="1" @checked(old('consent_given')) required> Share my contact details with this institution.</label>
                <button class="button teal">Send enquiry</button>
            </form>
        </section>
        <section class="panel"><h2>Own this institution?</h2><a class="button outline" href="{{ route('institute.claim', $listing->slug) }}">Claim this listing →</a></section>
    </aside>
</div>
@if($similarListings->isNotEmpty())
    <section class="wrap block"><div class="heading"><h2>Similar institutions nearby</h2></div>
    <div class="card-grid">@foreach($similarListings as $similar) @include('directory.card', ['listing' => $similar]) @endforeach</div></section>
@endif
@endsection
