/**
 * Cookie consent banner (Stage 13).
 *
 * Ответственность модуля строго ограничена UI-баннера согласия:
 *  - прочитать cookie согласия и проверить, что значение валидно;
 *  - показать/скрыть баннер;
 *  - сохранить выбор посетителя в first-party cookie с нужными атрибутами;
 *  - для обычных выборов — сохранить cookie и скрыть баннер без перезагрузки
 *    страницы (аналитика, если разрешена, включится на следующей обычной
 *    навигации — сервер сам решает, показывать ли её, на основании
 *    отправленного cookie);
 *  - если посетитель отзывает уже действующее согласие v1_all в пользу
 *    v1_necessary, страница перезагружается, потому что аналитика могла уже
 *    исполняться в текущем документе, и перезагрузка — простой способ
 *    остановить её под новым серверным гейтингом;
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

    function saveChoice(value, banner) {
        var previousConsent = readCookie(COOKIE_NAME);
        writeConsentCookie(value);

        // Отзыв уже действующего согласия v1_all -> v1_necessary: аналитика
        // могла уже исполняться в текущем документе, поэтому перезагружаем
        // страницу, чтобы серверный гейтинг применился к текущему документу.
        if (previousConsent === 'v1_all' && value === 'v1_necessary') {
            window.location.reload();
            return;
        }

        if (banner) {
            banner.hidden = true;
        }
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
                saveChoice('v1_all', banner);
            });
        }

        if (acceptNecessaryBtn) {
            acceptNecessaryBtn.addEventListener('click', function () {
                saveChoice('v1_necessary', banner);
            });
        }

        function reopenBanner() {
            if (!banner) {
                return;
            }

            banner.hidden = false;
            banner.scrollIntoView({block: 'end', behavior: 'smooth'});

            if (acceptAllBtn) {
                acceptAllBtn.focus();
            }
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

        // Дополнительные триггеры «Настроить cookie» (например, из панели
        // карты на главной). id="cookie-settings-open" в подвале сохранён
        // как есть для обратной совместимости и тестов; эти элементы лишь
        // переоткрывают тот же баннер и НЕ выдают согласие сами по себе.
        var extraSettingsTriggers = document.querySelectorAll('[data-cookie-settings-open]');
        Array.prototype.forEach.call(extraSettingsTriggers, function (trigger) {
            if (trigger === settingsOpenBtn) {
                return;
            }

            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                reopenBanner();
            });
        });
    });
})();
