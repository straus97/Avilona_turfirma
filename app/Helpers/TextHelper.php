<?php
//этот файл помогает правильно выводить новости на сайт
namespace App\Helpers;

class TextHelper
{
    public static function formatNewsDescription($html)
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $paragraphs = $doc->getElementsByTagName('p');

        if ($paragraphs->length > 0) {
            $firstParagraph = $paragraphs->item(0)->textContent;
            return mb_strimwidth($firstParagraph, 0, 150, "...");
        }

        return 'Описание отсутствует'; // Возвращаем пустую строку, если нет тегов <p>
    }
}
