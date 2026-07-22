<?php echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"; ?>
<rss version="2.0">
<channel>
<title>Новости Авилона</title>
<link>{{ route('helpful_news.index') }}</link>
<description>Лента новостей туристической фирмы</description>
<language>ru</language>
@foreach ($items as $item)
<item>
<title>{{ $item->title }}</title>
<link>{{ $item->link }}</link>
<description>{{ $item->description }}</description>
<guid>{{ $item->link }}</guid>
@if (!is_null($item->pub_date))
<pubDate>{{ \Carbon\Carbon::parse($item->pub_date)->toRssString() }}</pubDate>
@endif
</item>
@endforeach
</channel>
</rss>
