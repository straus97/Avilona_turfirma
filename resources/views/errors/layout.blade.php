{{--
    E4-B: самостоятельный макет системных страниц ошибок (403/419/429/500/503, 4xx/5xx).

    Намеренно не наследует layouts.main / cabinet.layouts.app: страница ошибки
    должна отрисоваться, даже если недоступны БД, сессия, кеш или включён режим
    обслуживания. Здесь нет обращений к Auth, моделям, уведомлениям и внешним CDN;
    стили инлайновые. Ссылки строятся route()-хелпером с безопасным запасным
    вариантом (route() не трогает сессию/БД).
--}}
@php
    $homeUrl = rescue(fn () => route('home.index'), '/', false);
    $contactUrl = rescue(fn () => route('contact.index'), '/contacts', false);
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Ошибка') | avilona.ru</title>
    <style>
        :root {
            --err-ink: #1f2933;
            --err-muted: #52606d;
            --err-sea: #0277bd;
            --err-sea-strong: #01466f;
            --err-accent: #98410b;
            --err-border: #dce3e8;
            --err-bg: #f2f8fc;
            --err-surface: #ffffff;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: var(--err-bg);
            color: var(--err-ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.5;
        }
        .err-brand {
            margin: 0 0 16px;
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: .02em;
            color: var(--err-sea-strong);
        }
        .err-brand span { color: var(--err-accent); font-weight: 600; }
        .err-card {
            width: 100%;
            max-width: 560px;
            padding: 32px 24px;
            text-align: center;
            background: var(--err-surface);
            border: 1px solid var(--err-border);
            border-top: 4px solid var(--err-sea);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(1, 70, 111, .08);
        }
        .err-card__art { display: block; margin: 0 auto 8px; width: 96px; height: 64px; }
        .err-code {
            margin: 0;
            font-size: .875rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--err-accent);
        }
        .err-code strong {
            display: block;
            font-size: 4rem;
            line-height: 1.1;
            letter-spacing: 0;
            color: var(--err-sea-strong);
        }
        .err-title { margin: 8px 0 12px; font-size: 1.625rem; line-height: 1.25; color: var(--err-ink); }
        .err-message { margin: 0 auto 8px; max-width: 44ch; color: var(--err-muted); font-size: 1rem; }
        .err-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
            margin-top: 24px;
        }
        .err-btn {
            display: inline-block;
            min-height: 44px;
            padding: 10px 20px;
            border: 2px solid var(--err-sea-strong);
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            line-height: 1.5;
            text-decoration: none;
            color: var(--err-sea-strong);
            background: var(--err-surface);
        }
        .err-btn--primary { color: #ffffff; background: var(--err-sea-strong); }
        .err-btn:hover { background: #e3f1fa; }
        .err-btn--primary:hover { background: var(--err-sea); border-color: var(--err-sea); }
        .err-btn:focus-visible { outline: 3px solid var(--err-accent); outline-offset: 3px; }
        @media (max-width: 480px) {
            .err-card { padding: 24px 16px; }
            .err-code strong { font-size: 3.25rem; }
            .err-title { font-size: 1.375rem; }
            .err-btn { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
    <p class="err-brand">Авилона <span>· туристическая фирма</span></p>
    <main class="err-card">
        <svg class="err-card__art" viewBox="0 0 96 64" aria-hidden="true" focusable="false">
            <circle cx="70" cy="18" r="9" fill="#f3b98a"/>
            <path d="M4 44c10 0 10-6 20-6s10 6 20 6 10-6 20-6 10 6 20 6 8-3 12-4v26H4z" fill="#0277bd" opacity=".85"/>
            <path d="M4 54c10 0 10-6 20-6s10 6 20 6 10-6 20-6 10 6 20 6 8-3 12-4v14H4z" fill="#01466f"/>
        </svg>
        <p class="err-code">Ошибка <strong>@yield('code')</strong></p>
        <h1 class="err-title">@yield('heading')</h1>
        <p class="err-message">@yield('message')</p>
        <div class="err-actions">
            <a href="{{ $homeUrl }}" class="err-btn err-btn--primary">Вернуться на главную</a>
            <a href="{{ $contactUrl }}" class="err-btn">Связаться с нами</a>
            @yield('extra_action')
        </div>
    </main>
</body>
</html>
