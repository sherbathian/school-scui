<form class="search" action="{{ $action }}" method="get" role="search">
    @if(isset($filters['view']))<input type="hidden" name="view" value="{{ $filters['view'] }}">@endif
    <label>Institution or neighbourhood<input type="search" name="q" placeholder="e.g. F-8, Bahria Town, or a school name" maxlength="100" value="{{ $filters['q'] ?? '' }}"></label>
    <label>City<select name="city"><option value="">All cities</option>
        @foreach($cities as $city)<option value="{{ $city->slug }}" @selected(($filters['city'] ?? '') === $city->slug)>{{ $city->name }}</option>@endforeach
    </select></label>
    <button class="button teal" type="submit">Search directory <span aria-hidden="true">→</span></button>
</form>
