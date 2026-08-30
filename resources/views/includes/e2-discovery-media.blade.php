{{--
    E2-A3-I1 — медиа для discovery-карточек и detail-страниц.
    Параметры include:
      $mediaSrc     — строка URL или null/'' (отсутствует)
      $mediaAlt     — осмысленный alt для значимого изображения ('' для декоративного)
      $mediaClass   — класс контейнера ('e2-card__media' по умолчанию)
      $mediaLoading — 'lazy' по умолчанию

    Никогда не выводит <img src="">: при отсутствии источника рисуется
    не-<img> CSS/HTML-заглушка из дизайн-системы E2 (Bootstrap Icons).
    Без inline onerror.
--}}
@php
    $mediaSrc = trim((string) ($mediaSrc ?? ''));
    $mediaAlt = $mediaAlt ?? '';
    $mediaClass = $mediaClass ?? 'e2-card__media';
    $mediaLoading = $mediaLoading ?? 'lazy';
@endphp
@if($mediaSrc !== '')
    <div class="{{ $mediaClass }}">
        <img src="{{ $mediaSrc }}" alt="{{ $mediaAlt }}" loading="{{ $mediaLoading }}">
    </div>
@else
    <div class="{{ $mediaClass }} e2-media-placeholder" aria-hidden="true">
        <i class="bi bi-image" aria-hidden="true"></i>
    </div>
@endif
