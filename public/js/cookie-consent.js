/**
 * Cookie consent banner (Stage 13).
 *
 * Ответственность модуля строго ограничена UI-баннера согласия:
 *  - прочитать cookie согласия и проверить, что значение валидно;
 *  - показать/скрыть баннер;
 *  - сохранить выбор посетителя в first-party cookie с нужными атрибутами;
 *  - перезагрузить страницу после сохранения выбора (сервер сам решит,
 *    показывать ли аналитику, на основании отправленного cookie);
 *  - повторно открыть баннер по действию "Настройки cookie" в подвале.
 *
 * Модуль не собирает аналитику, не отправляет сетевые запросы и не хранит
 * никаких персональных данных — только сам факт выбора версии согласия.
 */
(function () {
    'use strict';

    var COOKIE_NAME = 'avilona_cookie_consent';
    var VALID_VALUES = ['v1_all', 'v1_necessary'];
    var COOKIE_MAX_AGE_SECONDS = 31536000; // 1 год

    function readCookie(name) {
        var pattern = new RegExp(
            '(?:^|; )' + name.replace(/([.$?*|{}()\[\]\\\/+^])/g, '\\$1') + '=([^;]*)'
        );
        var match = document.cookie.match(pattern);

        return match ? decodeURIComponent(match[1]) : null;
    }

    function hasValidConsent() {
        return VALID_VALUES.indexOf(readCookie(COOKIE_NAME)) !== -1;
    }

    function writeConsentCookie(value) {
        var attributes = [
            COOKIE_NAME + '=' + encodeURIComponent(value),
            'Path=/',
            'Max-Age=' + COOKIE_MAX_AGE_SECONDS,
            'SameSite=Lax'
        ];

        if (window.location.protocol === 'https:') {
            attributes.push('Secure');
        }

        document.cookie = attributes.join('; ');
    }

    function saveChoiceAndReload(value) {
        writeConsentCookie(value);
        window.location.reload();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var banner = document.getElementById('cookie-consent-banner');
        var acceptAllBtn = document.getElementById('cookie-consent-accept-all');
        var acceptNecessaryBtn = document.getElementById('cookie-consent-accept-necessary');
        var settingsOpenBtn = document.getElementById('cookie-settings-open');

        if (banner) {
            // Подстраховка на клиенте (например, если разметка пришла из
            // кэша cache.response, отданного для другого посетителя):
            // фактическое состояние cookie в браузере — источник истины.
            banner.hidden = hasValidConsent();
        }

        if (acceptAllBtn) {
            acceptAllBtn.addEventListener('click', function () {
                saveChoiceAndReload('v1_all');
            });
        }

        if (acceptNecessaryBtn) {
            acceptNecessaryBtn.addEventListener('click', function () {
                saveChoiceAndReload('v1_necessary');
            });
        }

        if (settingsOpenBtn) {
            settingsOpenBtn.addEventListener('click', function () {
                if (!banner) {
                    return;
                }

                banner.hidden = false;
                banner.scrollIntoView({block: 'end', behavior: 'smooth'});

                if (acceptAllBtn) {
                    acceptAllBtn.focus();
                }
            });
        }
    });
})();
