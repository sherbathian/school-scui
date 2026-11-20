@extends('directory.layout')
@section('title', $mode === 'register' ? 'Create account' : 'Sign in')
@section('robots')<meta name="robots" content="noindex">@endsection
@section('content')
<div class="wrap block">
    <section class="panel institute-form">
        <h1>{{ $mode === 'register' ? ($directoryAccount ? 'Create your TaleemFinder account' : 'Create an institute account') : ($directoryAccount ? 'Sign in to TaleemFinder' : 'Sign in to your institute account') }}</h1>
        @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
        <form method="post" action="{{ $mode === 'register' ? route($directoryAccount ? 'directory.sign-up' : 'institute.register') : route($directoryAccount ? 'directory.sign-in' : 'login') }}">
            @csrf
            <input type="hidden" name="listing" value="{{ is_string($listing) ? $listing : '' }}">
            @if($mode === 'register')<label>Full name<input name="name" required maxlength="150" autocomplete="name" value="{{ old('name') }}"></label>@endif
            <label>Email address<input type="email" name="email" required maxlength="190" autocomplete="username" value="{{ old('email') }}"></label>
            @if($mode === 'register')
                <label>Contact phone (optional)<input type="tel" name="phone" maxlength="30" autocomplete="tel" value="{{ old('phone') }}"></label>
            @endif
            <label>Password<input type="password" name="password" required autocomplete="{{ $mode === 'register' ? 'new-password' : 'current-password' }}" @if($mode === 'register') minlength="8" maxlength="72" @endif></label>
            @if($mode === 'register')<label>Confirm password<input type="password" name="password_confirmation" required autocomplete="new-password"></label>@endif
            <button class="button teal">{{ $mode === 'register' ? 'Create account' : 'Sign in' }}</button>
        </form>
        @if($mode === 'register')
            <p>Already registered? <a href="{{ route($directoryAccount ? 'directory.sign-in' : 'login', is_string($listing) ? ['listing' => $listing] : []) }}">Sign in</a></p>
        @else
            <p><a href="{{ route($directoryAccount ? 'directory.sign-up' : 'institute.register', is_string($listing) ? ['listing' => $listing] : []) }}">Create an account</a></p>
        @endif
    </section>
</div>
@endsection
