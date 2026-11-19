@extends('directory.layout')
@section('title', 'Claim '.$school->name)
@section('robots')<meta name="robots" content="noindex">@endsection
@section('content')
<div class="wrap block">
    <section class="panel institute-form">
        <h1>Claim {{ $school->name }}</h1>
        @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
        @if($claim?->status === 'verified')
            <p>Your claim has been approved.</p>
        @elseif($claim?->status === 'pending')
            <p>Your request is awaiting platform review.</p>
        @elseif($school->claim_status === 'claimed')
            <p>This institution has already been claimed.</p>
        @else
            @if($claim?->status === 'rejected')<p>Your previous request was declined. You can submit a new request.</p>@endif
            <p>Provide a phone number where the TaleemFinder team can reach you to verify your relationship to the institution.</p>
            <form method="post" action="{{ route('institute.claim', $school->slug) }}">
                @csrf
                <label>Full name<input name="claimant_name" required maxlength="150" value="{{ old('claimant_name', auth()->user()->name) }}"></label>
                <label>Role at the institution<input name="claimant_role" maxlength="100" value="{{ old('claimant_role') }}"></label>
                <label>Contact phone<input type="tel" name="phone" required maxlength="30" value="{{ old('phone') }}"></label>
                <button class="button teal">Submit claim</button>
            </form>
        @endif
        <p><a href="{{ route('directory.profile', $school->slug) }}">Back to the institution profile</a></p>
    </section>
</div>
@endsection
