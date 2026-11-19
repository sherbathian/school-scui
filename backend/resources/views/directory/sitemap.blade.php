{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach(['directory.home', 'directory.index', 'directory.about', 'directory.contact'] as $page)
        <url><loc>{{ route($page) }}</loc></url>
    @endforeach
    @foreach(array_keys(\App\Http\Controllers\PublicDirectoryController::TYPES) as $category)
        <url><loc>{{ route('directory.category', $category) }}</loc></url>
    @endforeach
    @foreach($listings as $listing)
        <url><loc>{{ route('directory.profile', $listing->slug) }}</loc></url>
    @endforeach
</urlset>
