{{--
    E2-A5-I1 — карточка списка для публичных редакционных разделов
    («Новости» и «Интересные статьи»). Обе модели дают одинаковую анатомию
    карточки: медиа → заголовок-ссылка → тизер → опциональная дата публикации.

    Маленький явный партиал, НЕ обобщённый фреймворк:
      - ни News, ни Article не несут достоверных author/category — их здесь нет;
      - дата публикации есть только у Новостей (News.pub_date); Статьи дату
        не передают (нет надёжного поля — created_at/updated_at не публикуются);
      - заголовок карточки — это и есть осмысленная ссылка на детальную
        страницу (никаких N одинаковых «Подробнее» как единственной цели).

    Параметры include:
      $href      — URL детальной страницы (при пустом — карточка не кликабельна)
      $title     — заголовок (текст)
      $teaser    — уже готовый плоский тизер (экранируется здесь) или null
      $date      — уже отформатированная строка даты публикации или null
      $mediaSrc  — URL изображения или null/'' (тогда — E2 CSS/HTML-заглушка)
      $mediaAlt  — осмысленный alt для значимого изображения ('' — декоративное)
--}}
@php
    $href = trim((string) ($href ?? ''));
    $title = $title ?? 'Без названия';
    $teaser = $teaser ?? null;
    $date = $date ?? null;
    $mediaSrc = trim((string) ($mediaSrc ?? ''));
    $mediaAlt = $mediaAlt ?? $title;
@endphp
<article class="e2-card e2-editorial-card @if($href !== '') e2-card--link @endif">
    @include('includes.e2-discovery-media', [
        'mediaSrc' => $mediaSrc,
        'mediaAlt' => $mediaSrc !== '' ? $mediaAlt : '',
        'mediaClass' => 'e2-card__media',
    ])
    <div class="e2-card__body">
        <h2 class="e2-card__title">
            @if($href !== '')
                <a href="{{ $href }}" class="e2-card__link-target">{{ $title }}</a>
            @else
                {{ $title }}
            @endif
        </h2>
        @if(!empty($teaser))
            <p class="e2-card__text card-text">{{ $teaser }}</p>
        @endif
        @if(!empty($date))
            <p class="e2-card__meta e2-editorial-card__date">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                <span>{{ $date }}</span>
            </p>
        @endif
    </div>
</article>
