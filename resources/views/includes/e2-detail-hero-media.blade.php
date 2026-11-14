{{--
    NOV-PUBLIC-04B — медиа detail-страниц «Страны» / «Направления».
    Параметры include:
      $heroSrc   — большое изображение (image_large) или null/''
      $thumbSrc  — маленькое изображение (image_small) или null/''
      $mediaAlt  — alt для значимого изображения

    Большое изображение выводится в «геро»-рамке как раньше. Если большого нет, миниатюра
    (в архиве 270×200) НЕ растягивается на рамку 16:9 / 21:9 — она выводится в маленьком
    слоте естественного размера. Если нет ни одного изображения — прежняя заглушка.
--}}
@php
    $heroSrc = trim((string) ($heroSrc ?? ''));
    $thumbSrc = trim((string) ($thumbSrc ?? ''));
    $mediaAlt = $mediaAlt ?? '';
@endphp
@if($heroSrc !== '')
    @include('includes.e2-discovery-media', ['mediaSrc' => $heroSrc, 'mediaAlt' => $mediaAlt, 'mediaClass' => 'e2-detail-media'])
@elseif($thumbSrc !== '')
    @include('includes.e2-discovery-media', ['mediaSrc' => $thumbSrc, 'mediaAlt' => $mediaAlt, 'mediaClass' => 'e2-detail-thumb'])
@else
    @include('includes.e2-discovery-media', ['mediaSrc' => '', 'mediaAlt' => '', 'mediaClass' => 'e2-detail-media'])
@endif
