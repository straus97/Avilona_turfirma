{{--
    E2-A4-I1 — плитка награды для страницы «Наши достижения».
    Единственный параметр include:
      $award — объект с полями контракта: id, image, category.
      Полей title / date / issuer / ranking / sort НЕТ — ничего из этого
      не выдумывается.

    P0-доступность: триггер модалки — нативный <button type="button">
    (фокусируемый, Enter/Space работают штатно), а не кликабельная картинка.
    Миниатюра — не интерактивный элемент. Модалка — декларативная Bootstrap
    (modal-dialog-centered, modal-lg), Esc и возврат фокуса — штатный
    Bootstrap, без кастомного JS. Никогда не выводит src="".
--}}
@php
    $awardId = $award->id;
    $category = trim((string) ($award->category ?? ''));
    $displayCategory = $category !== '' ? $category : 'Награда';
    $image = trim((string) ($award->image ?? ''));
    $modalId = 'awardModal' . $awardId;
    $labelId = 'awardModalLabel' . $awardId;
@endphp
<div class="e2-award-tile">
    @if($image !== '')
        <button type="button" class="e2-award-tile__trigger"
                data-bs-toggle="modal" data-bs-target="#{{ $modalId }}"
                aria-label="Открыть изображение награды: {{ $displayCategory }}">
            <span class="e2-award-tile__thumb">
                <img src="{{ $image }}" alt="{{ $displayCategory }}" loading="lazy">
            </span>
            <span class="e2-award-tile__caption">{{ $displayCategory }}</span>
        </button>

        <div class="modal fade e2-award-modal" id="{{ $modalId }}" tabindex="-1"
             aria-labelledby="{{ $labelId }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="{{ $labelId }}">{{ $displayCategory }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body e2-award-modal__body">
                        <img src="{{ $image }}" alt="Награда: {{ $displayCategory }}" class="e2-award-modal__image">
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="e2-award-tile__placeholder">
            <span class="e2-award-tile__thumb e2-award-tile__thumb--empty" aria-hidden="true">
                <i class="bi bi-award"></i>
            </span>
            <span class="e2-award-tile__caption">{{ $displayCategory }}</span>
        </div>
    @endif
</div>
