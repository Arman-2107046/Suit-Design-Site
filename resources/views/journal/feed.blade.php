<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ config('app.name') }} Journal</title>
        <link>{{ url('/journal') }}</link>
        <atom:link href="{{ url('/journal/feed') }}" rel="self" type="application/rss+xml" />
        <description>Suits, cloth, and the craft of dressing well.</description>
        <language>en</language>
        @foreach ($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ url('/journal/' . $post->slug) }}</link>
            <guid isPermaLink="true">{{ url('/journal/' . $post->slug) }}</guid>
            <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
            @if ($post->category)<category>{{ $post->category->name }}</category>@endif
            <description>{{ $post->excerptText() }}</description>
        </item>
        @endforeach
    </channel>
</rss>
