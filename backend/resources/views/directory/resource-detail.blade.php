@extends('directory.layout')
@section('title', $row->title)
@section('content')
<section class="hero directory-hero"><div class="wrap">
    <p class="breadcrumb"><a href="{{ route('directory.home') }}">Home</a> / <a href="{{ route('directory.resources', $resource) }}">{{ $collectionTitle }}</a></p>
    <h1>{{ $row->title }}</h1>
    @if(!empty($row->excerpt ?? $row->summary ?? null))<p class="intro">{{ $row->excerpt ?? $row->summary }}</p>@endif
</div></section>
<div class="wrap block">
    <article class="panel resource-detail">
        @if(!empty($row->school_name))<p><a href="{{ route('directory.profile', $row->school_slug) }}">{{ $row->school_name }}</a></p>@endif
        @php($facts = ['city_name' => 'City', 'venue' => 'Venue', 'author' => 'Author', 'subject' => 'Subject', 'condition' => 'Condition', 'listing_type' => 'Listing type', 'mode' => 'Delivery', 'level' => 'Level', 'eligibility' => 'Eligibility', 'education_level' => 'Education level', 'board_name' => 'Board', 'category_name' => 'Category'])
        <dl class="resource-facts">
            @foreach($facts as $field => $label)
                @if(!empty($row->$field))<div><dt>{{ $label }}</dt><dd>{{ $row->$field }}</dd></div>@endif
            @endforeach
            @if(!empty($row->starts_at))<div><dt>Starts</dt><dd>{{ \Illuminate\Support\Carbon::parse($row->starts_at)->format('j M Y · g:i a') }}</dd></div>@endif
            @if(!empty($row->duration_value))<div><dt>Duration</dt><dd>{{ $row->duration_value }} {{ $row->duration_unit }}</dd></div>@endif
            @if(isset($row->price) || isset($row->fee) || isset($row->fee_amount))<div><dt>Price or fee</dt><dd>PKR {{ number_format($row->price ?? $row->fee ?? $row->fee_amount) }} {{ !empty($row->fee_period) ? '/ '.$row->fee_period : '' }}</dd></div>@endif
        </dl>
        @if(!empty($row->body ?? $row->description ?? null))<p class="description">{{ $row->body ?? $row->description }}</p>@endif
        @if(!empty($row->registration_url) && preg_match('/^https?:\/\//i', $row->registration_url))
            <a class="button teal" href="{{ $row->registration_url }}" rel="noopener noreferrer">Registration details ↗</a>
        @endif
        @if($resource === 'admissions' && !empty($row->school_slug))
            <a class="button teal" href="{{ route('directory.profile', $row->school_slug) }}#enquiry">Ask about admission</a>
        @endif
        @if($resource === 'study-resources' && preg_match('~\Astudy-files/[A-Za-z0-9]+\.pdf\z~', $row->file_path))
            <a class="button teal" href="{{ route('directory.study-download', $row->id) }}">Download PDF</a>
        @endif
        @if($resource === 'books')
            @auth
                @if((int)auth()->id() !== (int)$row->user_id)
                    <form class="resource-enquiry" method="post" action="{{ route('directory.book-enquiry', $row->id) }}">
                        @csrf
                        <label>Ask about this book<textarea name="message" rows="3" required maxlength="2000">{{ old('message') }}</textarea></label>
                        <button class="button teal">Send enquiry</button>
                    </form>
                @endif
            @else
                <a class="button outline" href="{{ route('directory.sign-in') }}">Sign in to enquire</a>
            @endauth
        @endif
        @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    </article>
</div>
@endsection
