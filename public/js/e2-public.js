/**
 * e2-public.js — общий интерактивный слой публичного сайта (E2-A2-I2).
 *
 * Заменяет в живом рантайме public/js/scripts_min.js. Содержит только то
 * общее поведение, которое обязано пережить миграцию:
 *
 *   A. Наполнение единой Bootstrap-модалки контактов менеджера
 *      (#managerContactModal) в зависимости от нажатой кнопки-триггера.
 *   B. Показ/скрытие и клик кнопки прокрутки наверх (#button-up).
 *
 * Ванильный JavaScript. Открытие/закрытие, фокус-трап, Escape, возврат
 * фокуса и backdrop делает сам Bootstrap 5 через data-атрибуты триггеров —
 * здесь нет ни window.onclick, ни ручного display:block/none.
 *
 * Совместимостных глобалей (openModal/openContactModal/openWhatsApp/…) файл
 * намеренно НЕ восстанавливает: в текущем источнике их вызывали только
 * layouts/main.blade.php и home.blade.php, и оба переведены на декларативные
 * триггеры в этом же слайсе.
 */
(function () {
    'use strict';

    /* ---------------------------------------------------------------------
     * A. Единая модалка контактов менеджера
     * ------------------------------------------------------------------- */

    /**
     * Модалка держит в разметке строки всех менеджеров сразу. Триггер
     * сообщает режим через data-атрибуты:
     *
     *   data-manager-mode="single" + data-manager-phone="+7XXXXXXXXXX"
     *       — показываем одну строку конкретного менеджера
     *         (телефоны в шапке/подвале);
     *   data-manager-mode="all" (или отсутствует)
     *       — показываем всех менеджеров
     *         (обобщённые CTA «Связаться с менеджером»).
     */
    function initManagerContactModal() {
        var modal = document.getElementById('managerContactModal');
        if (!modal) {
            return;
        }

        var intro = modal.querySelector('[data-manager-intro]');
        var rows = Array.prototype.slice.call(
            modal.querySelectorAll('[data-manager-row]')
        );
        if (!rows.length) {
            return;
        }

        var introAll = intro ? intro.getAttribute('data-intro-all') : null;
        var introSingle = intro ? intro.getAttribute('data-intro-single') : null;

        function normalizePhone(value) {
            return (value || '').replace(/[^\d+]/g, '');
        }

        modal.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget || null;
            var mode = trigger ? trigger.getAttribute('data-manager-mode') : null;
            var wantedPhone = trigger
                ? normalizePhone(trigger.getAttribute('data-manager-phone'))
                : '';

            var single = mode === 'single' && wantedPhone !== '';
            var matched = 0;

            rows.forEach(function (row) {
                var rowPhone = normalizePhone(row.getAttribute('data-manager-phone'));
                var show = !single || rowPhone === wantedPhone;
                row.hidden = !show;
                if (show) {
                    matched += 1;
                }
            });

            // Защитный фолбэк: если конкретный номер не совпал ни с одной
            // строкой (рассинхрон разметки) — показываем всех, чтобы модалка
            // никогда не открывалась пустой.
            if (single && matched === 0) {
                rows.forEach(function (row) {
                    row.hidden = false;
                });
                single = false;
            }

            if (intro) {
                if (single && introSingle) {
                    intro.textContent = introSingle;
                } else if (!single && introAll) {
                    intro.textContent = introAll;
                }
            }
        });
    }

    /* ---------------------------------------------------------------------
     * B. Кнопка «Наверх» (#button-up)
     *
     * Сохраняем принятое в E2-A2-I1 поведение:
     *   — на десктопе (ширина окна >= 1024px) появляется после прокрутки
     *     страницы больше чем на 100px;
     *   — на более узких экранах всегда скрыта;
     *   — клик плавно прокручивает к началу страницы;
     *   — клавиатура работает сама, потому что это <button>.
     *
     * Показ/скрытие выражаем инлайновым display (legacy #button-up в
     * style_min.css стартует со display:none), как это делал scripts_min.js
     * через jQuery.fadeIn()/fadeOut(). CSS не трогаем.
     * ------------------------------------------------------------------- */

    function initButtonUp() {
        var button = document.getElementById('button-up');
        if (!button) {
            return;
        }

        var DESKTOP_MIN_WIDTH = 1024;
        var SCROLL_THRESHOLD = 100;

        function currentScrollTop() {
            return window.pageYOffset ||
                document.documentElement.scrollTop ||
                document.body.scrollTop || 0;
        }

        function syncVisibility() {
            var wideEnough = window.innerWidth >= DESKTOP_MIN_WIDTH;
            var scrolledEnough = currentScrollTop() > SCROLL_THRESHOLD;
            button.style.display = (wideEnough && scrolledEnough) ? 'block' : 'none';
        }

        var prefersReducedMotion = window.matchMedia &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        button.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                behavior: prefersReducedMotion ? 'auto' : 'smooth'
            });
        });

        window.addEventListener('scroll', syncVisibility, { passive: true });
        window.addEventListener('resize', syncVisibility);
        syncVisibility();
    }

    /* ------------------------------------------------------------------- */

    function init() {
        initManagerContactModal();
        initButtonUp();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
