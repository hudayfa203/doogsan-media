{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
    <title>Doogsan Media ({{ editions()[$locale]['native'] }})</title>
    <link>{{ lroute('home', [], $locale) }}</link>
    <atom:link href="{{ lroute('rss', [], $locale) }}" rel="self" type="application/rss+xml"/>
    <description>{{ lsetting('site_description', '', $locale) }}</description>
    <language>{{ $locale }}</language>
    <lastBuildDate>{{ now()->toRssString() }}</lastBuildDate>
    @foreach ($items as $t)
    <item>
        <title>{{ $t->title }}</title>
        <link>{{ $t->url() }}</link>
        <guid isPermaLink="true">{{ $t->url() }}</guid>
        <pubDate>{{ $t->published_at->toRssString() }}</pubDate>
        @if ($t->article->author)<dc:creator>{{ $t->article->author->name }}</dc:creator>@endif
        @if ($t->article->category)<category>{{ $t->article->category->nameFor($locale) }}</category>@endif
        <description>{{ $t->standfirst }}</description>
        @if ($t->article->featuredMedia)<media:content url="{{ $t->article->featuredMedia->url(1600) }}" medium="image"/>@endif
    </item>
    @endforeach
</channel>
</rss>
