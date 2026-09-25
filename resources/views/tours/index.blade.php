@extends('layouts.main')

@section('title', 'Поиск туров - Туристическая фирма Авилона')
@section('meta_description', 'Поиск туров из Санкт-Петербурга и Москвы у ведущих туроператоров. Выберите тур и оставьте заявку — менеджер Авилоны проверит цену и наличие мест и свяжется с вами.')

{{-- E5-A1: PoC поиска туров на базе официального встраиваемого модуля Tourvisor.
     Модуль 9981450 — публичная конфигурация встраивания (не секрет). Внутренний UI
     модуля НЕ переопределяется: локально стилизуются только обёртка, отступы и
     блок-подсказка. Ключ API Tourvisor здесь не используется и в код не попадает.
     Прежний локальный каталог/форма и Sletat/Coral-код на этой странице больше не
     рендерятся (сервисы и API-маршруты сохранены до отдельного среза очистки). --}}
@section('styles')
<style>
    .e5-tours-search {
        /* резервируем место, чтобы страница не «прыгала», пока модуль загружается */
        min-height: 18rem;
    }
    .e5-tours-search__module {
        min-width: 0;
        max-width: 100%;
    }
    /* Широкая форма Tourvisor имеет собственную min-width 680px. На мобильных устройствах модуль сам
       переключается на мобильную форму; в узком окне десктопного браузера форма прокручивается внутри
       блока, а не расширяет страницу. */
    @media (max-width: 767.98px) {
        .e5-tours-search__module {
            overflow-x: auto;
        }
    }
    .e5-tours-steps {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 15rem), 1fr));
        margin: 0;
        padding: 0;
        list-style: none;
    }
</style>
@endsection

@section('content')
    <main>
        <div class="container">
            @include('includes.e2-breadcrumb', ['items' => [
                ['label' => 'Главная', 'url' => route('home.index')],
                ['label' => 'Поиск туров', 'url' => null],
            ]])

            <section class="e2-page-hero" aria-labelledby="e2-page-hero-title">
                <h1 id="e2-page-hero-title" class="e2-page-hero__title">Поиск туров</h1>
                <p class="e2-page-hero__intro">Подберите тур у ведущих туроператоров: укажите город вылета, направление,
                    даты и состав туристов. Цены и наличие мест могут меняться. Отправка заявки — это не бронирование и
                    не оплата: менеджер Авилоны проверит выбранное предложение и свяжется с вами.</p>
            </section>

            <section class="e2-section e5-tours-search" aria-label="Форма поиска туров">
                {{-- Официальный код встраивания Tourvisor. Скрипт подключается один раз, только на этой странице. --}}
                <div class="tv-search-form tv-moduleid-9981450 e5-tours-search__module" id="tourvisor-module"></div>

                <noscript>
                    <div class="e2-alert e2-alert--info" role="note">
                        Для поиска туров на сайте нужен включённый JavaScript. Вы можете связаться с менеджером —
                        он подберёт тур вместе с вами.
                    </div>
                </noscript>

                {{-- Показывается только если модуль так и не появился спустя разумное время. --}}
                <div class="e2-alert e2-alert--info mt-3" id="tourvisor-delay-notice" role="status" hidden>
                    Форма поиска пока не отобразилась. Возможно, она загружается дольше обычного или заблокирована
                    вашим браузером. Обновите страницу или свяжитесь с менеджером — мы подберём тур вместе с вами.
                </div>
            </section>

            <section class="e2-section" aria-labelledby="e5-tours-how-title">
                <div class="e2-section__head">
                    <h2 id="e5-tours-how-title" class="e2-section__title">Как это работает</h2>
                </div>
                <ol class="e5-tours-steps">
                    <li class="e2-card">
                        <div class="e2-card__body">
                            <h3 class="e2-card__title">1. Выберите тур</h3>
                            <p class="e2-card__text">Воспользуйтесь поиском выше и оставьте заявку на понравившееся предложение.</p>
                        </div>
                    </li>
                    <li class="e2-card">
                        <div class="e2-card__body">
                            <h3 class="e2-card__title">2. Менеджер проверит предложение</h3>
                            <p class="e2-card__text">Мы уточним актуальную цену и наличие мест и свяжемся с вами.</p>
                        </div>
                    </li>
                    <li class="e2-card">
                        <div class="e2-card__body">
                            <h3 class="e2-card__title">3. Бронирование — после согласования</h3>
                            <p class="e2-card__text">Заявка сама по себе тур не бронирует и ничего не оплачивает. Бронируем вместе с вами.</p>
                        </div>
                    </li>
                </ol>
            </section>

            <section class="e2-section" aria-labelledby="e5-tours-contact-title">
                <div class="e2-section__head">
                    <h2 id="e5-tours-contact-title" class="e2-section__title">Нужна помощь с подбором?</h2>
                    <p class="e2-section__intro">Не нашли подходящий вариант или не получается воспользоваться поиском —
                        напишите или позвоните менеджеру.</p>
                </div>
                <div class="e2-page-hero__actions">
                    <button type="button" class="e2-btn e2-btn--primary"
                            data-bs-toggle="modal" data-bs-target="#managerContactModal"
                            data-manager-mode="all">Связаться с менеджером</button>
                    <a class="e2-btn e2-btn--tertiary" href="{{ route('contact.index') }}">Страница контактов</a>
                </div>
            </section>
        </div>
    </main>
@endsection

@push('scripts')
{{-- Tourvisor: официальный загрузчик модуля (HTTPS-вариант адреса из сгенерированного кода). async — чтобы
     недоступность внешнего хоста не блокировала разбор страницы и подвал. --}}
<script src="https://tourvisor.ru/module/init.js" async></script>
<script>
    (function () {
        var box = document.getElementById('tourvisor-module');
        var notice = document.getElementById('tourvisor-delay-notice');
        if (!box || !notice) {
            return;
        }

        function syncNotice() {
            // Подсказка нужна только пока модуль не отрисовался.
            if (box.childElementCount > 0) {
                notice.hidden = true;
                return true;
            }
            return false;
        }

        if ('MutationObserver' in window) {
            new MutationObserver(syncNotice).observe(box, {childList: true});
        }

        // Не показываем ошибку сразу: внешнему модулю нужно время на загрузку.
        window.setTimeout(function () {
            if (!syncNotice()) {
                notice.hidden = false;
            }
        }, 10000);
    })();
</script>
@endpush
