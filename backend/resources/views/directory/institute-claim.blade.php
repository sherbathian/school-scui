@extends('directory.layout')
@section('title', 'Claim '.$school->name)
@section('robots')<meta name="robots" content="noindex">@endsection
@section('content')
<div class="wrap block">
    <section class="panel institute-form">
        <h1>Claim {{ $school->name }}</h1>
        @if(session('claim_message'))<p role="status">{{ session('claim_message') }}</p>@endif
        @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
        @if($claim?->status === 'verified')
            <p>Your claim has been approved.</p>
        @elseif($claim?->status === 'pending')
            @if($claim->email_verified_at && $claim->email === auth()->user()->email)
                <p>Your email is verified. Your request is awaiting platform review.</p>
            @else
                <p>Verify your account email to continue. The code is sent to {{ auth()->user()->email }}.</p>
                <form method="post" action="{{ route('institute.claim.send-code', $school->slug) }}">
                    @csrf
                    <button class="button teal">Send verification code</button>
                </form>
                <form method="post" action="{{ route('institute.claim.verify-code', $school->slug) }}">
                    @csrf
                    <label>Verification code<input name="code" required inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6"></label>
                    <button class="button teal">Verify email</button>
                </form>
            @endif
        @elseif($claim?->status === 'rejected' && $school->listing_status !== 'published')
            <p>Your claim was declined.</p>
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
