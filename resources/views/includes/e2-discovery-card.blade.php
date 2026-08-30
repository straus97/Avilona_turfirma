{{--
    E2-A3-I1 — общая карточка списка для Стран и Направлений (одинаковая
    анатомия: медиа → заголовок → тизер → опциональная мета, вся карточка —
    интуитивная цель клика при наличии slug). Специальные предложения имеют
    другую модель взаимодействия (явная кнопка «Подробнее») и свою разметку.

    Параметры include:
      $href       — URL detail-страницы или null (пустой slug => карточка не кликабельна)
      $title      — заголовок (текст)
      $teaser     — уже очищенный plain-тизер или null
      $meta       — строка метаданных (например категория) или null
      $mediaSrc   — URL картинки или null
      $mediaAlt   — alt для картинки
--}}
@php
    $href = $href ?? null;
    $title = $title ?? 'Без названия';
    $teaser = $teaser ?? null;
    $meta = $meta ?? null;
    $mediaSrc = $mediaSrc ?? null;
    $mediaAlt = $mediaAlt ?? $title;
@endphp
<article class="e2-card @if(! empty($href)) e2-card--link @endif">
    @include('includes.e2-discovery-media', [
        'mediaSrc' => $mediaSrc,
        'mediaAlt' => ! empty($mediaSrc) ? $mediaAlt : '',
        'mediaClass' => 'e2-card__media',
    ])
    <div class="e2-card__body">
        <h2 class="e2-card__title">
            @if(! empty($href))
                <a href="{{ $href }}" class="e2-card__link-target">{{ $title }}</a>
            @else
                {{ $title }}
            @endif
        </h2>
        @if(! empty($teaser))
            <p class="e2-card__text">{{ $teaser }}</p>
        @endif
        @if(! empty($meta))
            <p class="e2-card__meta">{{ $meta }}</p>
        @endif
    </div>
</article>
