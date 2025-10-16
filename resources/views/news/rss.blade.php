<?php
$rss = simplexml_load_file('https://www.turizm.ru/news/rss/yandex/');
if ($rss) {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?><rss version="2.0"></rss>');
    $channel = $xml->addChild('channel');
    $channel->addChild('title', htmlspecialchars($rss->channel->title));
    $channel->addChild('link', htmlspecialchars($rss->channel->link));
    $channel->addChild('description', htmlspecialchars($rss->channel->description));
    $channel->addChild('language', htmlspecialchars($rss->channel->language));
    $channel->addChild('lastBuildDate', gmdate('D, d M Y H:i:s T', strtotime($rss->channel->lastBuildDate)));
    foreach ($rss->channel->item as $item) {
        $new_item = $channel->addChild('item');
        $new_item->addChild('title', htmlspecialchars($item->title));
        $new_item->addChild('description', htmlspecialchars($item->children('content', true)->encoded));
        $new_item->addChild('link', htmlspecialchars($item->link));
        $new_item->addChild('pubDate', gmdate('D, d M Y H:i:s T', strtotime($item->pubDate)));
        $new_item->addChild('guid', htmlspecialchars($item->guid));
    }
    $xml->asXML('news.rss');
}
