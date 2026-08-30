{{--
    E2-A3-I1 — общий конверсионный блок для detail-страниц discovery.
    Параметры include:
      $listUrl   — URL «вернуться к списку» (третичная контекстная ссылка), может быть null
      $listLabel — подпись для $listUrl

    CTA:
      - первичная: подбор тура (только ссылка на home #tour-search, механика — E5)
      - вторичная: общая Bootstrap-модалка менеджеров (#managerContactModal, mode=all)
      - третичная: контекстная ссылка на список
    JS не добавляет.
--}}
@php
    $listUrl = $listUrl ?? null;
    $listLabel = $listLabel ?? 'Ко всем разделам';
@endphp
<section class="e2-cta-band" aria-labelledby="e2-next-step-title">
    <h2 id="e2-next-step-title" class="e2-cta-band__title">Не нашли подходящий вариант?</h2>
    <p class="e2-cta-band__text">Можно перейти к подбору тура или связаться с менеджером — поможем
        выбрать поездку под ваши даты и бюджет.</p>
    <div class="e2-cta-band__actions">
        <a class="e2-btn e2-btn--primary" href="{{ route('home.index') }}#tour-search">Подобрать тур</a>
        <button type="button" class="e2-btn e2-btn--secondary"
                data-bs-toggle="modal" data-bs-target="#managerContactModal"
                data-manager-mode="all">Связаться с менеджером</button>
        @if(! empty($listUrl))
            <a class="e2-btn e2-btn--tertiary" href="{{ $listUrl }}">{{ $listLabel }}</a>
        @endif
    </div>
</section>
