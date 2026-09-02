{{--
    E2-A4-I1 — карточка сотрудника для страницы «Наш коллектив».
    Единственный параметр include:
      $employee — объект с полями контракта: id, name, position, tel, email,
                  whatsapp, vk, image. Ничего сверх контракта не выводится
                  (без биографий, опыта, квалификаций, статусов).

    Правила контактов:
      - видимый телефон и цель ссылки tel: — это ровно поле `tel`
        (телефон не маршрутизируется через whatsapp);
      - одно действие email (mailto:), без дублирующей иконки-ссылки;
      - whatsapp нормализуется для wa.me (только цифры); ссылка рисуется
        только если результат непустой;
      - vk: берётся как есть при http(s)://, префикс https:// при vk.com/,
        иначе внешнее действие не рисуется (без битых относительных ссылок).
    Никогда не выводит src="".
--}}
@php
    $displayName = trim((string) ($employee->name ?? ''));
    $displayName = $displayName !== '' ? $displayName : 'Сотрудник';
    $position = trim((string) ($employee->position ?? ''));
    $tel = trim((string) ($employee->tel ?? ''));
    $email = trim((string) ($employee->email ?? ''));
    $image = trim((string) ($employee->image ?? ''));

    $whatsappDigits = preg_replace('/\D+/', '', (string) ($employee->whatsapp ?? ''));

    $vkRaw = trim((string) ($employee->vk ?? ''));
    $vkUrl = null;
    if (\Illuminate\Support\Str::startsWith($vkRaw, ['http://', 'https://'])) {
        $vkUrl = $vkRaw;
    } elseif (\Illuminate\Support\Str::startsWith($vkRaw, 'vk.com/')) {
        $vkUrl = 'https://' . $vkRaw;
    }
@endphp
<article class="e2-person-card">
    <div class="e2-person-card__media">
        @if($image !== '')
            <img src="{{ $image }}" alt="{{ $displayName }}" loading="lazy">
        @else
            <span class="e2-person-card__media-placeholder" aria-hidden="true">
                <i class="bi bi-person"></i>
            </span>
        @endif
    </div>
    <div class="e2-person-card__body">
        <h2 class="e2-person-card__name">{{ $displayName }}</h2>
        @if($position !== '')
            <p class="e2-person-card__position">{{ $position }}</p>
        @endif

        @if($tel !== '' || $email !== '' || $whatsappDigits !== '' || $vkUrl !== null)
            <ul class="e2-person-card__contacts">
                @if($tel !== '')
                    <li>
                        <a class="e2-person-card__contact" href="tel:{{ $tel }}">
                            <i class="bi bi-telephone" aria-hidden="true"></i>
                            <span>{{ $tel }}</span>
                        </a>
                    </li>
                @endif
                @if($email !== '')
                    <li>
                        <a class="e2-person-card__contact" href="mailto:{{ $email }}">
                            <i class="bi bi-envelope" aria-hidden="true"></i>
                            <span>{{ $email }}</span>
                        </a>
                    </li>
                @endif
                @if($whatsappDigits !== '')
                    <li>
                        <a class="e2-person-card__contact" href="https://wa.me/{{ $whatsappDigits }}"
                           target="_blank" rel="noopener"
                           aria-label="Написать в WhatsApp — {{ $displayName }}">
                            <i class="bi bi-whatsapp" aria-hidden="true"></i>
                            <span>WhatsApp</span>
                        </a>
                    </li>
                @endif
                @if($vkUrl !== null)
                    <li>
                        <a class="e2-person-card__contact" href="{{ $vkUrl }}"
                           target="_blank" rel="noopener"
                           aria-label="Профиль ВКонтакте — {{ $displayName }}">
                            <i class="bi bi-people" aria-hidden="true"></i>
                            <span>ВКонтакте</span>
                        </a>
                    </li>
                @endif
            </ul>
        @endif
    </div>
</article>
