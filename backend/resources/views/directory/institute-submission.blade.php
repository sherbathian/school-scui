@extends('directory.layout')
@section('title', 'Add an institution')
@section('robots')<meta name="robots" content="noindex">@endsection
@section('content')
<div class="wrap block">
    <section class="panel institute-form">
        <h1>Add an institution</h1>
        <p>Already listed? <a href="{{ route('directory.index') }}">Find and claim its public profile</a>.</p>
        @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
        <form method="post" action="{{ route('institute.new') }}">
            @csrf
            <label>Institution name<input name="name" required maxlength="255" value="{{ old('name') }}"></label>
            <label>Institution type<select name="institute_type" required>
                @foreach($types as [$value, $label])<option value="{{ $value }}" @selected(old('institute_type') === $value)>{{ $label }}</option>@endforeach
            </select></label>
            <label>Street address<input name="address_line" maxlength="255" value="{{ old('address_line') }}"></label>
            <label>Your role<input name="claimant_role" maxlength="100" value="{{ old('claimant_role') }}"></label>
            <label>Private contact phone<input type="tel" name="phone" required maxlength="30" value="{{ old('phone') }}"></label>
            <button class="button teal">Submit for review</button>
        </form>
        @if($submissions->isNotEmpty())
            <h2 class="submission-heading">Your submissions</h2>
            <ul>
                @foreach($submissions as $submission)
                    <li>{{ $submission->name }} · {{ ['verified' => 'Claim approved', 'rejected' => 'Claim declined', 'pending' => 'Awaiting review'][$submission->status] ?? 'Withdrawn' }}</li>
                @endforeach
            </ul>
        @endif
        <form method="post" action="{{ route('institute.logout') }}">@csrf<button class="button outline">Sign out</button></form>
    </section>
</div>
@endsection
