/**
 * cabinet-chat.js — общее прогрессивное улучшение чат-поверхностей кабинета (E3).
 *
 * Одна реализация поведения для трёх ролевых поверхностей (турист / менеджер /
 * админ); ролевые различия задаются через data-атрибуты в Blade. Скрипт
 * загружается на всех страницах кабинета, но:
 *   - очистку черновиков при выходе вешает всегда;
 *   - улучшение чата включает ТОЛЬКО при наличии [data-chat-root].
 *
 * Контракты:
 *   - ссылки веток остаются настоящими <a href>; при сбое fetch — обычная
 *     серверная навигация по этому href (прогрессивное улучшение);
 *   - переключение ветки — асинхронная подмена фрагмента [data-chat-root],
 *     отрисованного тем же авторизованным ролевым маршрутом (новых роутов нет);
 *   - опрос сообщений — прежний JSON-контракт messages.index, ровно один таймер
 *     на активную переписку, устаревшие ответы не попадают в другую ветку;
 *   - черновики текста — только localStorage, на пользователя + контекст + заявку;
 *   - вложения никогда не хранятся в localStorage.
 */
(function () {
    'use strict';

    var DRAFT_PREFIX = 'avilona:chat-draft:v1:';
    var DRAFT_DEBOUNCE_MS = 400;

    // Взводится при явном выходе: после этого черновики текущего пользователя
    // удалены и НЕ должны пересохраняться обработчиками pagehide/beforeunload,
    // иначе только что очищенный черновик вернулся бы после повторного входа.
    var loggingOut = false;

    // ------------------------------------------------------------------
    // localStorage — все обращения защищены (приватный режим, отключённое
    // хранилище, выброс исключения при доступе).
    // ------------------------------------------------------------------
    function lsGet(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }
    function lsSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (e) { /* noop */ }
    }
    function lsRemove(key) {
        try { window.localStorage.removeItem(key); } catch (e) { /* noop */ }
    }
    function lsKeys() {
        var keys = [];
        try {
            for (var i = 0; i < window.localStorage.length; i++) {
                keys.push(window.localStorage.key(i));
            }
        } catch (e) { /* noop */ }
        return keys;
    }

    function cabinetUserId() {
        var meta = document.querySelector('meta[name="cabinet-user-id"]');
        var raw = meta ? (meta.getAttribute('content') || '') : '';
        return raw.trim();
    }

    function draftKey(userId, context, bookingId) {
        return DRAFT_PREFIX + userId + ':' + context + ':' + bookingId;
    }

    // ------------------------------------------------------------------
    // Очистка черновиков при ЯВНОМ выходе (на всех страницах кабинета).
    // Удаляются только ключи чата текущего аутентифицированного пользователя.
    // Полная очистка хранилища не выполняется, чужой неймспейс не трогаем.
    // ------------------------------------------------------------------
    function clearCurrentUserChatDrafts() {
        // Помечаем выход даже без userId — pagehide/beforeunload не должны
        // пересохранять черновик на уходящей странице.
        loggingOut = true;

        var userId = cabinetUserId();
        if (!userId) { return; }
        var ownPrefix = DRAFT_PREFIX + userId + ':';

        lsKeys().forEach(function (key) {
            if (key && key.indexOf(ownPrefix) === 0) {
                lsRemove(key);
            }
        });
    }

    function isLogoutForm(form) {
        if (!form || form.nodeName !== 'FORM') { return false; }
        if (form.hasAttribute('data-cabinet-logout')) { return true; }
        // Резервная эвристика по действию — POST на .../logout.
        var action = form.getAttribute('action') || '';
        return /\/logout\/?(?:[?#]|$)/.test(action);
    }

    function bindLogoutCleanup() {
        // Реальный браузерный поток выхода бывает двух видов: обычная отправка
        // POST-формы и активация submit-кнопки (в т.ч. touch / клавиатура).
        // Вешаем оба хука в фазе capture — до ухода со страницы. Двойной вызов
        // очистки безопасен. Логаут остаётся обычным POST + CSRF (не AJAX).
        document.addEventListener('submit', function (event) {
            if (isLogoutForm(event.target)) { clearCurrentUserChatDrafts(); }
        }, true);

        document.addEventListener('click', function (event) {
            var control = event.target.closest
                ? event.target.closest('button, input[type="submit"], [type="submit"]')
                : null;
            if (!control) { return; }
            var form = control.form || (control.closest ? control.closest('form') : null);
            if (isLogoutForm(form)) { clearCurrentUserChatDrafts(); }
        }, true);
    }

    // ------------------------------------------------------------------
    // Мелкие DOM-помощники (безопасное создание узлов, без innerHTML для
    // пользовательских данных).
    // ------------------------------------------------------------------
    function el(tag, className) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        return node;
    }

    function iconNode(iconClass) {
        var i = el('i', iconClass);
        i.setAttribute('aria-hidden', 'true');
        return i;
    }

    function formatDateTime(value) {
        var d = new Date(value);
        if (isNaN(d.getTime())) { return String(value || ''); }
        try {
            return d.toLocaleString('ru-RU', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        } catch (e) {
            return d.toISOString();
        }
    }

    function isNearBottom(container) {
        return container.scrollHeight - container.scrollTop - container.clientHeight < 80;
    }

    function scrollToBottom(container) {
        if (container) { container.scrollTop = container.scrollHeight; }
    }

    // ------------------------------------------------------------------
    // Улучшение конкретной чат-поверхности.
    // ------------------------------------------------------------------
    function initChat(initialRoot) {
        var ctx = {
            root: initialRoot,
            context: initialRoot.getAttribute('data-chat-context') || 'tourist',
            userId: initialRoot.getAttribute('data-chat-user-id') || cabinetUserId(),
            bookingId: initialRoot.getAttribute('data-chat-current-booking-id') || null,
            messagesUrl: initialRoot.getAttribute('data-chat-messages-url') || '',
            unreadUrl: initialRoot.getAttribute('data-chat-unread-url') || '',
            peerName: initialRoot.getAttribute('data-chat-peer-name') || 'Менеджер',
            pollMs: parseInt(initialRoot.getAttribute('data-chat-poll-ms') || '5000', 10) || 5000,
            currentUrl: window.location.href,
            generation: 0,
            pollTimer: null,
            pollAbort: null,
            switchAbort: null,
            draftTimer: null
        };

        // ---- Черновики ------------------------------------------------
        function currentInput() { return ctx.root.querySelector('[data-chat-input]'); }

        function saveDraftNow() {
            if (loggingOut) { return; } // выход: черновики уже очищены, не воскрешаем
            if (!ctx.bookingId) { return; }
            var input = currentInput();
            if (!input) { return; }
            var key = draftKey(ctx.userId, ctx.context, ctx.bookingId);
            if (input.value && input.value.trim() !== '') {
                lsSet(key, input.value);
            } else {
                lsRemove(key);
            }
        }

        function restoreDraft() {
            if (!ctx.bookingId) { return; }
            var input = currentInput();
            if (!input) { return; }
            var stored = lsGet(draftKey(ctx.userId, ctx.context, ctx.bookingId));
            if (stored != null && input.value === '') {
                input.value = stored;
            }
        }

        function scheduleDraftSave() {
            if (ctx.draftTimer) { window.clearTimeout(ctx.draftTimer); }
            ctx.draftTimer = window.setTimeout(saveDraftNow, DRAFT_DEBOUNCE_MS);
        }

        // ---- Вложения ----------------------------------------------
        function attachmentInput() { return ctx.root.querySelector('[data-chat-attachment]'); }

        function hasSelectedAttachment() {
            var input = attachmentInput();
            return !!(input && input.files && input.files.length > 0);
        }

        function clearAttachment(scope) {
            var root = scope || ctx.root;
            var input = root.querySelector('[data-chat-attachment]');
            if (input) { input.value = ''; }
            var name = root.querySelector('[data-chat-attachment-name]');
            if (name) { name.hidden = true; }
            var fileName = root.querySelector('[data-chat-attachment-filename]');
            if (fileName) { fileName.textContent = ''; }
        }

        // ---- Строка статуса / индикатор загрузки --------------------
        function announce(text) {
            var status = ctx.root.querySelector('[data-chat-status]');
            if (status) { status.textContent = text; }
        }

        function setLoading(isLoading) {
            ctx.root.classList.toggle('tc-chat--loading', !!isLoading);
            if (isLoading) {
                ctx.root.setAttribute('aria-busy', 'true');
                announce('Загрузка переписки…');
            } else {
                ctx.root.removeAttribute('aria-busy');
            }
        }

        function showError(message) {
            var box = ctx.root.querySelector('[data-chat-error]');
            if (!box) { window.alert(message); return; }
            box.textContent = message;
            box.hidden = false;
        }

        function clearError() {
            var box = ctx.root.querySelector('[data-chat-error]');
            if (box) { box.hidden = true; box.textContent = ''; }
        }

        // ---- Отрисовка сообщения (ролевые адаптеры) -----------------
        function messagesContainer() { return ctx.root.querySelector('[data-chat-messages]'); }

        function renderTouristMessage(message) {
            var mine = String(message.sender_id) === String(ctx.userId);
            var wrapper = el('div', 'tc-msg ' + (mine ? 'tc-msg--own' : 'tc-msg--other'));
            wrapper.setAttribute('data-message-id', message.id);

            var sender = el('div', 'tc-msg__sender');
            sender.textContent = mine ? 'Вы' : ctx.peerName;
            wrapper.appendChild(sender);

            var bubble = el('div', 'tc-msg__bubble');
            if (message.message) {
                var text = el('div');
                text.textContent = message.message;
                bubble.appendChild(text);
            }
            if (message.attachment_download_url) {
                var link = el('a', 'tc-msg__attachment' + (mine ? ' text-white' : ''));
                link.href = message.attachment_download_url;
                link.target = '_blank';
                link.rel = 'noopener';
                link.appendChild(iconNode('bi bi-paperclip'));
                link.appendChild(document.createTextNode(' Вложение'));
                bubble.appendChild(link);
            }
            wrapper.appendChild(bubble);

            var time = el('div', 'tc-msg__time');
            time.textContent = formatDateTime(message.created_at);
            wrapper.appendChild(time);
            return wrapper;
        }

        function renderStaffMessage(message) {
            var mine = String(message.sender_id) === String(ctx.userId);
            var wrapper = el('div', 'mb-3 d-flex ' + (mine ? 'justify-content-end' : 'justify-content-start'));
            wrapper.setAttribute('data-message-id', message.id);

            var inner = el('div');
            inner.style.maxWidth = '70%';

            var bubble = el('div', 'p-3 rounded ' + (mine ? 'bg-primary text-white' : 'bg-light'));
            if (message.message) {
                var text = el('div');
                text.style.fontSize = '0.875rem';
                text.textContent = message.message;
                bubble.appendChild(text);
            }
            if (message.attachment_download_url) {
                var attWrap = el('div', 'mt-2');
                var link = el('a', 'text-decoration-underline ' + (mine ? 'text-white' : 'text-primary'));
                link.href = message.attachment_download_url;
                link.target = '_blank';
                link.rel = 'noopener';
                link.appendChild(iconNode('bi bi-paperclip'));
                link.appendChild(document.createTextNode(' Вложение'));
                attWrap.appendChild(link);
                bubble.appendChild(attWrap);
            }

            var time = el('div', mine ? 'text-end' : '');
            time.style.fontSize = '0.75rem';
            time.style.color = '#9ca3af';
            time.style.marginTop = '0.25rem';
            time.textContent = formatDateTime(message.created_at);

            inner.appendChild(bubble);
            inner.appendChild(time);
            wrapper.appendChild(inner);
            return wrapper;
        }

        function renderMessage(message) {
            return ctx.context === 'tourist'
                ? renderTouristMessage(message)
                : renderStaffMessage(message);
        }

        function appendMessages(messages) {
            var container = messagesContainer();
            if (!container) { return 0; }

            var seen = {};
            container.querySelectorAll('[data-message-id]').forEach(function (node) {
                seen[node.getAttribute('data-message-id')] = true;
            });

            var hadMessages = !!container.querySelector('[data-message-id]');
            var near = isNearBottom(container);
            var added = 0;

            messages.forEach(function (message) {
                if (seen[String(message.id)]) { return; }
                seen[String(message.id)] = true;
                container.appendChild(renderMessage(message));
                added++;
            });

            if (added && !hadMessages) {
                // Убираем плейсхолдер «сообщений пока нет» (узлы без data-message-id).
                Array.prototype.slice.call(container.children).forEach(function (child) {
                    if (!child.hasAttribute('data-message-id')) {
                        container.removeChild(child);
                    }
                });
            }
            if (added && (near || !hadMessages)) {
                scrollToBottom(container);
            }
            return added;
        }

        // ---- Опрос (JSON messages.index, прежний контракт) ----------
        function stopPoll() {
            if (ctx.pollTimer) { window.clearInterval(ctx.pollTimer); ctx.pollTimer = null; }
            if (ctx.pollAbort) { ctx.pollAbort.abort(); ctx.pollAbort = null; }
        }

        function startPoll() {
            stopPoll();
            if (!ctx.bookingId || !ctx.messagesUrl || !messagesContainer()) { return; }
            ctx.pollTimer = window.setInterval(pollOnce, ctx.pollMs);
        }

        function pollOnce() {
            if (!ctx.bookingId) { return; }
            var myGeneration = ctx.generation;
            var myBookingId = ctx.bookingId;

            if (ctx.pollAbort) { ctx.pollAbort.abort(); }
            ctx.pollAbort = window.AbortController ? new AbortController() : null;

            var sep = ctx.messagesUrl.indexOf('?') > -1 ? '&' : '?';
            fetch(ctx.messagesUrl + sep + 'booking_id=' + encodeURIComponent(myBookingId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: ctx.pollAbort ? ctx.pollAbort.signal : undefined
            }).then(function (res) {
                if (!res.ok) { throw new Error('poll status ' + res.status); }
                return res.json();
            }).then(function (data) {
                // Устаревший ответт: пользователь уже переключил ветку.
                if (myGeneration !== ctx.generation || myBookingId !== ctx.bookingId) { return; }
                if (!Array.isArray(data)) { return; }
                appendMessages(data);
            }).catch(function () { /* сеть/отмена — игнорируем, следующий тик повторит */ });
        }

        // ---- Бейдж непрочитанных в сайдбаре -------------------------
        function refreshNavUnread() {
            if (!ctx.unreadUrl) { return; }
            var link = document.querySelector('[data-chat-nav-unread]');
            if (!link) { return; }
            fetch(ctx.unreadUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (res) {
                if (!res.ok) { throw new Error('unread status ' + res.status); }
                return res.json();
            }).then(function (payload) {
                var count = payload && typeof payload.count !== 'undefined' ? parseInt(payload.count, 10) : 0;
                var badge = link.querySelector('.menu-badge');
                if (count > 0) {
                    if (!badge) {
                        badge = el('span', 'menu-badge');
                        link.appendChild(badge);
                    }
                    badge.textContent = String(count);
                } else if (badge) {
                    badge.parentNode.removeChild(badge);
                }
            }).catch(function () { /* оставляем серверное значение */ });
        }

        // ---- Переключение ветки (подмена фрагмента) -----------------
        function focusConversation(root) {
            var target = root.querySelector('a[data-chat-thread][aria-current="page"]')
                || root.querySelector('[data-chat-window]')
                || root;
            try { target.focus({ preventScroll: true }); } catch (e) { try { target.focus(); } catch (e2) { /* noop */ } }
        }

        function applyRoot(newRoot, url, push) {
            stopPoll();
            ctx.root.replaceWith(newRoot);
            ctx.root = newRoot;
            ctx.bookingId = newRoot.getAttribute('data-chat-current-booking-id') || null;
            // URL опроса/непрочитанных берём строго из нового фрагмента: у
            // администратора это ветка-зависимо — назначенная ему заявка отдаёт
            // messages-url и композер, чужая (режим наблюдателя) — нет. Липкое
            // сохранение прежнего значения запускало бы опрос на чужой ветке.
            ctx.messagesUrl = newRoot.getAttribute('data-chat-messages-url') || '';
            ctx.unreadUrl = newRoot.getAttribute('data-chat-unread-url') || '';
            ctx.peerName = newRoot.getAttribute('data-chat-peer-name') || ctx.peerName;
            ctx.currentUrl = url;

            setLoading(false);
            clearError();
            restoreDraft();
            scrollToBottom(messagesContainer());

            if (push) {
                try { window.history.pushState({ chatUrl: url }, '', url); } catch (e) { /* noop */ }
            }

            var heading = newRoot.querySelector('[data-chat-window] h1, [data-chat-window] h2, [data-chat-window] h5');
            announce(heading ? ('Открыта переписка: ' + heading.textContent.trim()) : 'Переписка обновлена');
            focusConversation(newRoot);
            refreshNavUnread();
            startPoll();
        }

        function switchThread(url, options) {
            options = options || {};

            saveDraftNow();

            if (!options.skipAttachmentPrompt && hasSelectedAttachment()) {
                var proceed = window.confirm(
                    'Прикреплённый файл не переносится в другой чат.\n' +
                    'Переключиться и убрать выбранный файл?'
                );
                if (!proceed) { return; }
                clearAttachment();
            } else if (options.skipAttachmentPrompt && hasSelectedAttachment()) {
                clearAttachment();
            }

            var myGeneration = ++ctx.generation;
            stopPoll();
            if (ctx.switchAbort) { ctx.switchAbort.abort(); }
            ctx.switchAbort = window.AbortController ? new AbortController() : null;
            setLoading(true);

            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                credentials: 'same-origin',
                signal: ctx.switchAbort ? ctx.switchAbort.signal : undefined
            }).then(function (res) {
                if (!res.ok) { throw new Error('switch status ' + res.status); }
                return res.text();
            }).then(function (html) {
                if (myGeneration !== ctx.generation) { return; } // ответ устарел
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newRoot = doc.querySelector('[data-chat-root]');
                if (!newRoot) { throw new Error('no chat root in response'); }
                applyRoot(document.importNode(newRoot, true), url, options.push !== false);
            }).catch(function (error) {
                if (error && error.name === 'AbortError') { return; }
                // Ответ устарел (пользователь уже переключился дальше) — не трогаем
                // текущую ветку и не уводим со страницы.
                if (myGeneration !== ctx.generation) { return; }
                // Прогрессивное улучшение: обычная серверная навигация по href.
                window.location.assign(url);
            });
        }

        // ---- Отправка сообщения (AJAX, существующий messages.store) --
        function submitComposer(form) {
            if (form.getAttribute('data-chat-sending') === '1') { return; }

            var input = form.querySelector('[data-chat-input]');
            var button = form.querySelector('[type="submit"]');
            var fileInput = form.querySelector('[data-chat-attachment]');
            var bookingField = form.querySelector('[name="booking_id"]');
            var bookingIdAtSend = bookingField ? bookingField.value : ctx.bookingId;

            var text = input && input.value ? input.value.trim() : '';
            var hasFile = !!(fileInput && fileInput.files && fileInput.files.length > 0);
            if (!text && !hasFile) { return; }

            var storeUrl = form.getAttribute('action');
            var formData = new FormData(form);
            var tokenMeta = document.querySelector('meta[name="csrf-token"]');
            var token = tokenMeta ? tokenMeta.getAttribute('content') : formData.get('_token');

            form.setAttribute('data-chat-sending', '1');
            if (button) { button.disabled = true; }
            clearError();

            fetch(storeUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token || ''
                }
            }).then(function (res) {
                return res.text().then(function (raw) {
                    var body = {};
                    try { body = raw ? JSON.parse(raw) : {}; } catch (e) { body = {}; }
                    return { status: res.status, ok: res.ok, body: body };
                });
            }).then(function (result) {
                if (result.ok && result.body && result.body.message) {
                    // Черновик этой ветки очищаем только после подтверждённой записи.
                    lsRemove(draftKey(ctx.userId, ctx.context, bookingIdAtSend));
                    if (input) { input.value = ''; }
                    clearAttachment(form);

                    // Отрисовываем сохранённое сообщение только если ветка не сменилась.
                    if (String(ctx.bookingId) === String(bookingIdAtSend)) {
                        appendMessages([result.body.message]);
                        scrollToBottom(messagesContainer());
                    }
                    return;
                }
                if (result.status === 422) {
                    showError(firstValidationError(result.body) || 'Проверьте сообщение и повторите отправку.');
                } else if (result.status === 401 || result.status === 419) {
                    showError('Сессия истекла. Обновите страницу и войдите заново — черновик сохранён.');
                } else if (result.status === 403 || result.status === 404) {
                    showError('Нет доступа к этой переписке. Черновик сохранён.');
                } else {
                    showError('Не удалось отправить сообщение. Попробуйте ещё раз — черновик сохранён.');
                }
            }).catch(function () {
                showError('Нет соединения. Сообщение не отправлено, черновик сохранён.');
            }).then(function () {
                form.setAttribute('data-chat-sending', '0');
                if (button) { button.disabled = false; }
            });
        }

        function firstValidationError(body) {
            if (!body) { return null; }
            if (body.errors && typeof body.errors === 'object') {
                var keys = Object.keys(body.errors);
                if (keys.length && Array.isArray(body.errors[keys[0]]) && body.errors[keys[0]].length) {
                    return body.errors[keys[0]][0];
                }
            }
            return body.message || null;
        }

        // ---- Делегированные слушатели (переживают подмену фрагмента) -
        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0) { return; }
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }
            var link = event.target.closest ? event.target.closest('a[data-chat-thread]') : null;
            if (!link) { return; }
            if (!ctx.root.contains(link)) { return; }
            var url = link.getAttribute('href');
            if (!url) { return; }
            event.preventDefault();
            if (link.getAttribute('aria-current') === 'page') { return; }
            switchThread(url, { push: true });
        });

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form || !form.matches || !form.matches('[data-chat-composer]')) { return; }
            if (!ctx.root.contains(form)) { return; }
            event.preventDefault();
            submitComposer(form);
        });

        document.addEventListener('input', function (event) {
            var target = event.target;
            if (!target || !target.matches || !target.matches('[data-chat-input]')) { return; }
            if (!ctx.root.contains(target)) { return; }
            scheduleDraftSave();
        });

        document.addEventListener('change', function (event) {
            var target = event.target;
            if (!target || !target.matches || !target.matches('[data-chat-attachment]')) { return; }
            if (!ctx.root.contains(target)) { return; }
            var root = ctx.root;
            var name = root.querySelector('[data-chat-attachment-name]');
            var fileName = root.querySelector('[data-chat-attachment-filename]');
            if (target.files && target.files[0]) {
                if (fileName) { fileName.textContent = target.files[0].name; }
                if (name) { name.hidden = false; }
            } else if (name) {
                name.hidden = true;
            }
        });

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest ? event.target.closest('[data-chat-attachment-clear]') : null;
            if (!trigger || !ctx.root.contains(trigger)) { return; }
            event.preventDefault();
            clearAttachment();
        });

        window.addEventListener('popstate', function (event) {
            var url = (event.state && event.state.chatUrl) ? event.state.chatUrl : window.location.href;
            // Навигация уже произошла — не блокируем подтверждением, но черновик
            // сохраняем и выбранный файл убираем (перенос файла между ветками запрещён).
            switchThread(url, { push: false, skipAttachmentPrompt: true });
        });

        window.addEventListener('pagehide', saveDraftNow);
        window.addEventListener('beforeunload', saveDraftNow);

        // ---- Старт --------------------------------------------------
        try { window.history.replaceState({ chatUrl: window.location.href }, '', window.location.href); } catch (e) { /* noop */ }
        restoreDraft();
        scrollToBottom(messagesContainer());
        startPoll();
    }

    // ------------------------------------------------------------------
    // Загрузка.
    // ------------------------------------------------------------------
    function boot() {
        bindLogoutCleanup();
        var root = document.querySelector('[data-chat-root]');
        if (root) { initChat(root); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
