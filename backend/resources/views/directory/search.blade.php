<form class="search" action="{{ $action }}" method="get" role="search">
    @if(isset($filters['view']))<input type="hidden" name="view" value="{{ $filters['view'] }}">@endif
    <label>{{ ($homeSearch ?? false) ? 'Institution or sector' : 'Institution or neighbourhood' }}<span class="search-keyword">
        @if($homeSearch ?? false) @include('directory.icon', ['icon' => 'search']) @endif
        <input type="search" name="q" placeholder="e.g. F-8, Bahria Town, or a school name" maxlength="100" value="{{ $filters['q'] ?? '' }}"></span></label>
    <label>City<select name="city"><option value="">{{ ($homeSearch ?? false) ? 'Islamabad & Rawalpindi' : 'All cities' }}</option>
        @foreach($cities as $city)<option value="{{ $city->slug }}" @selected(($filters['city'] ?? '') === $city->slug)>{{ $city->name }}</option>@endforeach
    </select></label>
    @if($homeSearch ?? false)
        <label>Curriculum<select name="board"><option value="">All Tracks</option>
            @foreach($boards as $board)<option value="{{ $board->slug }}">{{ $board->name }}</option>@endforeach
        </select></label>
        <button class="button teal" type="submit">@include('directory.icon', ['icon' => 'search']) Search</button>
    @else
        <button class="button teal" type="submit">Search directory <span aria-hidden="true">→</span></button>
    @endif
</form>
