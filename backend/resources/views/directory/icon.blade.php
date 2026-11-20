<svg class="public-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($icon)
        @case('cap') <path d="m2 10 10-5 10 5-10 5-10-5Zm4 2v5c3 3 9 3 12 0v-5m4-2v5"/> @break
        @case('search') <circle cx="10.5" cy="10.5" r="6"/><path d="m15 15 4 4"/> @break
        @case('compare') <rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16m6-16v16"/> @break
        @case('bookmark') <path d="M6 4h12v17l-6-4-6 4z"/> @break
        @case('plus') <circle cx="12" cy="12" r="9"/><path d="M8 12h8m-4-4v8"/> @break
        @case('school') <path d="M3 21h18M5 21V10l7-6 7 6v11M10 21v-5h4v5"/><circle cx="12" cy="11" r="1.5"/> @break
        @case('college') <path d="M4 21h16M6 21V8h5V3h7v18m-8-9h4m-4 4h4m1-9h1"/> @break
        @case('university') <path d="m3 8 9-5 9 5H3zm2 12h14M6 10v7m6-7v7m6-7v7"/> @break
        @case('chip') <rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 10v4h6v-4H9zM9 3v3m6-3v3M9 18v3m6-3v3M3 9h3m-3 6h3m12-6h3m-3 6h3"/> @break
        @case('play') <circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4z"/> @break
        @case('book') <path d="M12 6c-3-2-6-2-9-1v14c3-1 6-1 9 1 3-2 6-2 9-1V5c-3-1-6-1-9 1zm0 0v14"/> @break
    @endswitch
</svg>
