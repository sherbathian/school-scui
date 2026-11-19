@extends('directory.layout')
@php($title = $collection === 'compare' ? 'Compare institutions' : 'Saved listings')
@section('title', $title)
@section('robots')<meta name="robots" content="noindex">@endsection
@section('content')
<section class="hero directory-hero"><div class="wrap"><h1>{{ $title }}</h1><p class="intro">Your shortlist in this browser.</p></div></section>
<section class="wrap block">
    @if($listings->isEmpty())<p class="empty">Your shortlist is empty. <a href="{{ route('directory.index') }}">Browse the directory →</a></p>
    @elseif($collection === 'compare')
        <div class="table-scroll"><table class="compare"><caption class="sr-only">Institution comparison</caption><thead><tr><th scope="col">Details</th>
            @foreach($listings as $listing)<th scope="col"><a href="{{ route('directory.profile', $listing->slug) }}">{{ $listing->name }}</a></th>@endforeach
        </tr></thead><tbody>
            @foreach(['City' => 'city_name', 'Neighbourhood' => 'area_name', 'Address' => 'address_line', 'Phone' => 'phone'] as $label => $field)
                <tr><th scope="row">{{ $label }}</th>@foreach($listings as $listing)<td>{{ $listing->{$field} ?: 'Not provided' }}</td>@endforeach</tr>
            @endforeach
            <tr><th scope="row">Shortlist</th>@foreach($listings as $listing)<td><form method="post" action="{{ route('directory.toggle', [$collection, $listing->slug]) }}">@csrf<button class="button outline small">Remove</button></form></td>@endforeach</tr>
        </tbody></table></div>
    @else
        <div class="results">@foreach($listings as $listing) @include('directory.card') @endforeach</div>
    @endif
</section>
@endsection
