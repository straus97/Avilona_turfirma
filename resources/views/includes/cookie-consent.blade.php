{{--
    Stage 13: баннер согласия на cookie.

    Видимость баннера определяется на сервере по валидному значению cookie
    avilona_cookie_consent (v1_all / v1_necessary), чтобы избежать мигания
    при загрузке страницы. public/js/cookie-consent.js дополнительно
    подстраховывает это на клиенте (например, если markup был отдан из кэша
    cache.response для другого посетителя) и обрабатывает сохранение выбора,
    перезагрузку страницы и повторное открытие баннера через "Настройки cookie".
--}}
@php
    $avilonaCookieConsentIsValid = \App\Support\CookieConsent::isValid(
        request()->cookie(\App\Support\CookieConsent::COOKIE_NAME)
    );
@endphp
<div
    id="cookie-consent-banner"
    class="cookie-consent-banner"
    role="dialog"
    aria-modal="false"
    aria-labelledby="cookie-consent-title"
    aria-describedby="cookie-consent-text"
    @if($avilonaCookieConsentIsValid) hidden @endif
>
    <div class="cookie-consent-banner__inner">
        <div class="cookie-consent-banner__text">
            <p id="cookie-consent-title" class="cookie-consent-banner__title">Мы используем cookie</p>
            <p id="cookie-consent-text" class="cookie-consent-banner__desc">
                Для работы сайта используются необходимые cookie. С вашего согласия мы также
                используем аналитические сервисы Яндекс.Метрика, LiveInternet и Top.Mail.Ru, чтобы
                оценивать посещаемость и улучшать сайт.
                <a href="{{ route('cookies.info') }}">Подробнее</a>
            </p>
        </div>
        <div class="cookie-consent-banner__actions">
            <button type="button" id="cookie-consent-accept-all"
                    class="btn btn-primary cookie-consent-banner__btn">Принять</button>
            <button type="button" id="cookie-consent-accept-necessary"
                    class="btn btn-outline-primary cookie-consent-banner__btn">Только необходимые</button>
        </div>
    </div>
</div>
