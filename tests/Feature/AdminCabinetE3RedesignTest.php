<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * E3-A5 — защитные контракты редизайна кабинета администратора.
 *
 * Проверяются устойчивые контракты (скоуп данных, честные пустые состояния,
 * соответствие списка назначаемых сотрудников единому правилу, отсутствие
 * N+1, структурный контракт денежных значений stat-card) — не точная
 * разметка или цвета. Разметочный контракт наблюдатель/назначенный
 * администратор в чате (composer скрыт/показан) проверяется здесь же ниже;
 * серверная авторизация чтения/записи чата (кто реально может отправить
 * сообщение через API) покрыта MessageParticipantAuthorizationTest.
 */
class AdminCabinetE3RedesignTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRoles(array $roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $roleName) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleName],
                ['description' => Role::availableRoles()[$roleName] ?? $roleName]
            );

            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function baseBookingAttributes(int $userId, ?int $managerId, string $status): array
    {
        return [
            'user_id' => $userId,
            'manager_id' => $managerId,
            'status' => $status,
            'departure_city' => 'Saint Petersburg',
            'destination_country' => 'Turkey',
            'destination_city' => 'Antalya',
            'start_date' => now()->addMonth()->toDateString(),
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
            'total_price' => 100000,
            'paid_amount' => 40000,
        ];
    }

    private function createBooking(array $attributes): Booking
    {
        return Booking::withoutEvents(fn (): Booking => Booking::query()->create($attributes));
    }

    // ------------------------------------------------------------------
    // A. Dashboard — честные пустые состояния и реальный скоуп
    // ------------------------------------------------------------------

    public function test_dashboard_renders_honest_empty_states_with_only_the_admin_present(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);

        $html = $this->actingAs($admin)->get(route('cabinet.admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Заявок пока нет.', $html);
        $this->assertStringContainsString('Менеджеров пока нет.', $html);
    }

    public function test_dashboard_counts_reflect_real_bookings_and_managers(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);

        $this->createBooking($this->baseBookingAttributes($tourist->id, $manager->id, Booking::STATUS_PROGRESS));
        $this->createBooking($this->baseBookingAttributes($tourist->id, null, Booking::STATUS_NEW));

        $html = $this->actingAs($admin)->get(route('cabinet.admin.dashboard'))->assertOk()->getContent();

        // Один менеджер, одна заявка без назначенного ответственного.
        $this->assertStringContainsString(e($manager->name), $html);
        $this->assertMatchesRegularExpression('/Без менеджера[\s\S]{0,400}>\s*1\s*</', $html);
    }

    public function test_dashboard_status_summary_shows_five_independent_canonical_counts(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);

        $counts = [
            Booking::STATUS_NEW => 2,
            Booking::STATUS_PROGRESS => 2,
            Booking::STATUS_CONFIRMED => 1,
            Booking::STATUS_COMPLETED => 3,
            Booking::STATUS_CANCELLED => 1,
        ];

        foreach ($counts as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $this->createBooking($this->baseBookingAttributes($tourist->id, $manager->id, $status));
            }
        }

        $html = $this->actingAs($admin)->get(route('cabinet.admin.dashboard'))->assertOk()->getContent();
        $statusCard = $this->extractStatusSummaryCard($html);

        // NEW и PROGRESS должны отображаться раздельно — регрессия на слитый
        // агрегат "pending" (NEW + PROGRESS = 4), показанный под меткой
        // "В обработке". Если счётчики снова смёржат, "В обработке" покажет 4,
        // а не 2, и этот ассерт провалится.
        foreach ($counts as $status => $count) {
            $label = preg_quote(Booking::availableStatuses()[$status], '/');
            $this->assertMatchesRegularExpression(
                '/' . $label . '[\s\S]{0,200}<strong>' . $count . '<\/strong>/',
                $statusCard,
                "Expected canonical status \"{$status}\" to render its own independent count of {$count}."
            );
        }
    }

    private function extractStatusSummaryCard(string $html): string
    {
        preg_match('/Заявки по статусам[\s\S]*?<\/table>/', $html, $matches);
        $this->assertNotEmpty($matches, 'Could not locate the "Заявки по статусам" card.');

        return $matches[0];
    }

    // ------------------------------------------------------------------
    // A.1 Dashboard — таблицы не создают page-level горизонтальный
    // оверфлоу на мобильных (browser-QA фикс E3-A5)
    // ------------------------------------------------------------------

    /**
     * Регрессия на browser-QA дефект: в отличие от каждой другой
     * admin-страницы с таблицами (finance/bonus/content/users/settings/...),
     * dashboard.blade.php не оборачивал ни одну из своих пяти таблиц в
     * .table-responsive. Таблица с table-layout:auto (в частности
     * "Менеджеры и нагрузка" с длинным email в отдельной колонке) вправе
     * стать шире родителя, и без .table-responsive это переполнение не
     * ограничено собственным скроллом таблицы — оно просачивается наружу и
     * становится page-level горизонтальным скроллом на узких экранах.
     * Обёртка переводит переполнение в локальный, ожидаемый скролл таблицы,
     * как на всех остальных admin-страницах, — это не блокирующий
     * `overflow-x: hidden` на body, маскирующий баг, а тот же контракт, что
     * уже используется везде в приложении.
     */
    public function test_dashboard_every_table_is_wrapped_in_table_responsive(): void
    {
        $source = file_get_contents(resource_path('views/admin/dashboard.blade.php'));
        $this->assertNotFalse($source);

        $tableCount = preg_match_all('/<table\b/', $source);
        $wrappedCount = preg_match_all('/<div class="table-responsive">\s*<table\b/', $source);

        $this->assertGreaterThan(0, $tableCount, 'Expected the dashboard to contain at least one <table>.');
        $this->assertSame(
            $tableCount,
            $wrappedCount,
            'Every <table> on the admin dashboard must be immediately wrapped in <div class="table-responsive"> — '
            . 'an auto-layout table can legitimately grow wider than its column, and without this wrapper that '
            . 'overflow leaks into page-level horizontal scroll instead of staying a local, expected table scroll.'
        );
    }

    /**
     * Поведенческая проверка того же контракта через реально отрендеренный
     * HTML: карточка "Менеджеры и нагрузка" (источник конкретного
     * browser-QA дефекта — самая широкая таблица дашборда, 4 колонки,
     * включая email) должна физически лежать внутри .table-responsive.
     */
    public function test_dashboard_managers_table_is_wrapped_in_table_responsive(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $html = $this->actingAs($admin)->get(route('cabinet.admin.dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<div class="table-responsive">\s*<table class="table align-middle">[\s\S]*?' . preg_quote($manager->name, '/') . '/u',
            $html,
            'The "Менеджеры и нагрузка" table must render inside a .table-responsive wrapper.'
        );
    }

    /**
     * Регрессия на browser-QA дефект транзитной ширины (~1000-1150px):
     * все 4 stat-card стояли в один ряд от md (768px) и выше через
     * голый col-md-3, из-за чего в диапазоне ~1000-1150px колонка была
     * слишком узкой — длинные метки ("БЕЗ МЕНЕДЖЕРА", "ВЫРУЧКА
     * (ЗАВЕРШЕНО)") наезжали на иконку. Раскладка обязана идти по
     * существующим брейкпоинтам Bootstrap: 1 колонка < md, 2 колонки
     * md..lg (голый col-md-3 без col-xl-3 даёт именно 4 узкие колонки в
     * этом диапазоне — регрессия), 4 колонки только с xl (>=1200px), где
     * места объективно достаточно.
     */
    public function test_dashboard_stat_cards_use_two_column_layout_below_xl_breakpoint(): void
    {
        $source = file_get_contents(resource_path('views/admin/dashboard.blade.php'));
        $this->assertNotFalse($source);

        $wrapperCount = preg_match_all('/<div class="col-md-6 col-xl-3">/', $source);
        $this->assertSame(
            4,
            $wrapperCount,
            'All 4 dashboard stat-card wrappers must use "col-md-6 col-xl-3": 1 column on mobile, '
            . '2 columns from md through lg (the ~1000-1150px transitional range), 4 columns only from xl (>=1200px) up.'
        );

        // Голый col-md-3 (без col-xl-3) — конкретная регрессия, из-за
        // которой уже с 768px и до бесконечности стояло 4 узкие колонки.
        $this->assertDoesNotMatchRegularExpression(
            '/<div class="col-md-3">/',
            $source,
            'A bare "col-md-3" wrapper on the dashboard stat-card row would force 4 narrow columns starting at '
            . '768px, reproducing the ~1000-1150px label/icon collision.'
        );
    }

    // ------------------------------------------------------------------
    // B. Доход/выручка — размер значения и защита от обрезки цифр
    // ------------------------------------------------------------------

    public function test_admin_money_call_sites_opt_in_explicitly(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);

        $dashboardHtml = $this->actingAs($admin)
            ->get(route('cabinet.admin.dashboard'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($dashboardHtml, 'admin-stat-value--money'));

        $financeHtml = $this->actingAs($admin)
            ->get(route('cabinet.admin.finance'))->assertOk()->getContent();
        $this->assertSame(3, substr_count($financeHtml, 'admin-stat-value--money'));

        $bonusHtml = $this->actingAs($admin)
            ->get(route('cabinet.admin.bonus'))->assertOk()->getContent();
        $this->assertSame(3, substr_count($bonusHtml, 'admin-stat-value--money'));
    }

    /**
     * Регрессия на конкретный баг из независимого ревью: инлайновый
     * font-size на .stat-card__value побеждал любой CSS-класс, из-за чего
     * clamp() в .admin-stat-value--money никогда не применялся. Базовый
     * размер должен жить в CSS-правиле .stat-card__value, а не в style="".
     */
    public function test_stat_card_component_has_no_inline_font_size_that_defeats_modifiers(): void
    {
        $componentSource = file_get_contents(resource_path('views/cabinet/components/stat-card.blade.php'));

        $this->assertNotFalse($componentSource);
        $this->assertDoesNotMatchRegularExpression(
            '/class="stat-card__value[^"]*"\s+style="[^"]*font-size\s*:/s',
            $componentSource,
            'stat-card__value must not carry an inline font-size — it defeats any CSS modifier class.'
        );
    }

    public function test_ordinary_stat_card_value_keeps_its_default_size_via_css(): void
    {
        $css = file_get_contents(public_path('css/cabinet-e3.css'));

        $this->assertNotFalse($css);
        $this->assertMatchesRegularExpression(
            '/\.stat-card__value\s*\{[^}]*font-size:\s*2rem;/s',
            $css,
            'Default stat-card value size must be a normal CSS rule so modifiers can override it via the cascade.'
        );
    }

    public function test_admin_money_modifier_overrides_default_size_with_responsive_clamp(): void
    {
        $moneyRuleBody = $this->extractMoneyModifierRuleBody();

        $this->assertMatchesRegularExpression('/white-space:\s*nowrap;/', $moneyRuleBody);
        $this->assertMatchesRegularExpression(
            '/font-size:\s*clamp\(/',
            $moneyRuleBody,
            'Money modifier must use a responsive clamp() size, not a fixed override.'
        );
        $this->assertStringNotContainsString(
            '!important',
            $moneyRuleBody,
            'The money-card size override must win via normal cascade specificity, not !important.'
        );
    }

    public function test_admin_money_modifier_does_not_hide_digits_with_ellipsis(): void
    {
        $moneyRuleBody = $this->extractMoneyModifierRuleBody();

        $this->assertStringNotContainsStringIgnoringCase('ellipsis', $moneyRuleBody);
        $this->assertStringNotContainsStringIgnoringCase('text-overflow', $moneyRuleBody);
    }

    /**
     * Регрессия на баг из browser-QA: на самой тесной раскладке — 4 карточки
     * в ряд на dashboard (col-md-3) — clamp() c верхней границей 2rem (та же,
     * что базовый .stat-card__value) даёт семизначной сумме вроде
     * "1 733 750 ₽" достаточно места, чтобы физически наехать на иконку
     * справа. Верхняя граница денежного модификатора должна быть заметно
     * меньше базового размера — иначе модификатор существует только на
     * бумаге и не решает тесноту именно узкой 4-колоночной раскладки.
     */
    public function test_admin_money_modifier_max_size_is_narrow_enough_to_avoid_icon_overlap(): void
    {
        $moneyRuleBody = $this->extractMoneyModifierRuleBody();

        preg_match(
            '/clamp\(\s*([\d.]+)rem\s*,[^,]+,\s*([\d.]+)rem\s*\)/',
            $moneyRuleBody,
            $matches
        );
        $this->assertNotEmpty($matches, 'Could not parse min/max rem bounds out of the money clamp().');

        [, $min, $max] = $matches;

        $this->assertLessThan(
            2.0,
            (float) $max,
            'Money clamp() max must be meaningfully below the base 2rem — at 2rem a 7-digit sum overlaps the stat-card icon in the 4-column dashboard row.'
        );
        $this->assertLessThanOrEqual(
            1.7,
            (float) $max,
            'Money clamp() max should stay within the narrow-desktop-safe range for a 4-column row.'
        );
        $this->assertLessThan((float) $max, (float) $min, 'Clamp min must stay below its max.');
    }

    /**
     * Регрессия на browser-QA полировку типографики (E3-A5): после фикса
     * транзитной раскладки (col-md-6 col-xl-3) денежное значение на
     * мобильной (<768px, 1 колонка) и на 2-колоночной (768-1199.98px)
     * раскладках всё ещё сидело на минимуме clamp() (0.75rem),
     * рассчитанном под САМУЮ тесную 4-колоночную раскладку (>=1200px). Там,
     * где колонка объективно в 2-3 раза шире, это выглядело как
     * второстепенный текст, а не как первичный KPI. Переопределения обязаны
     * жить на РЕАЛЬНЫХ границах той же сетки (md=768, xl=1200 — точно те
     * же, что у col-md-6 col-xl-3), а не на произвольных точках, и обязаны
     * быть заметно крупнее базового 4-колоночного минимума, оставаясь
     * меньше базового .stat-card__value (2rem), чтобы не слепо возвращать
     * старое переполняющее поведение.
     */
    public function test_admin_money_modifier_is_enlarged_on_wider_bands_with_ample_room(): void
    {
        $css = file_get_contents(public_path('css/cabinet-e3.css'));
        $this->assertNotFalse($css);

        preg_match(
            '/@media \(max-width:\s*767\.98px\)\s*\{\s*\.admin-stat-value--money\s*\{([^}]*)\}/s',
            $css,
            $mobileMatches
        );
        $this->assertNotEmpty(
            $mobileMatches,
            'Could not locate the mobile (<768px) override for .admin-stat-value--money at the same breakpoint as the grid (md).'
        );

        preg_match(
            '/@media \(min-width:\s*768px\)\s*and\s*\(max-width:\s*1199\.98px\)\s*\{\s*\.admin-stat-value--money\s*\{([^}]*)\}/s',
            $css,
            $tabletMatches
        );
        $this->assertNotEmpty(
            $tabletMatches,
            'Could not locate the 2-column (768-1199.98px) override for .admin-stat-value--money at the same breakpoints as the grid (md..xl).'
        );

        foreach (['mobile' => $mobileMatches[1], '2-column' => $tabletMatches[1]] as $label => $ruleBody) {
            preg_match('/font-size:\s*([\d.]+)rem/', $ruleBody, $sizeMatch);
            $this->assertNotEmpty($sizeMatch, "Could not parse font-size out of the {$label} money override.");
            $size = (float) $sizeMatch[1];

            $this->assertGreaterThan(
                1.0,
                $size,
                "The {$label} money override ({$size}rem) must be meaningfully bigger than the 0.75rem "
                . '4-column-safe floor to read as a primary KPI where the column has ample room.'
            );
            $this->assertLessThan(
                2.0,
                $size,
                "The {$label} money override ({$size}rem) must stay below the base .stat-card__value (2rem) — "
                . 'do not blindly restore the old oversized behavior.'
            );
            $this->assertStringNotContainsStringIgnoringCase('ellipsis', $ruleBody);
            $this->assertStringNotContainsString('!important', $ruleBody);
        }
    }

    /**
     * Регрессия на layout-первопричину наезда: если у иконки пропадёт
     * flex-shrink:0 или исчезнет явный gap между текстовой колонкой и
     * иконкой, длинная денежная строка снова сможет наехать на иконку
     * независимо от того, насколько мал font-size.
     */
    public function test_stat_card_icon_never_shrinks_and_keeps_an_explicit_gap_from_value_column(): void
    {
        $componentSource = file_get_contents(resource_path('views/cabinet/components/stat-card.blade.php'));
        $this->assertNotFalse($componentSource);

        // E4-D2: размеры иконки переехали из инлайн-стиля в CSS-класс, чтобы
        // их можно было менять по ширине карточки (@container). Инвариант тот
        // же — иконка не сжимается — и он проверяется на CSS-правиле.
        $this->assertStringContainsString('class="stat-card__icon"', $componentSource);
        $css = file_get_contents(public_path('css/cabinet-e3.css'));
        $this->assertNotFalse($css);
        preg_match('/\.stat-card__icon\s*\{([^}]*)\}/s', $css, $iconRule);
        $this->assertNotEmpty($iconRule, 'Could not locate the base .stat-card__icon rule.');
        $this->assertMatchesRegularExpression(
            '/flex:\s*0 0 auto;/',
            $iconRule[1],
            'The icon wrapper must not shrink so a long money value cannot compress it and slide underneath.'
        );
        $this->assertMatchesRegularExpression('/width:\s*60px;/', $iconRule[1]);
        $this->assertMatchesRegularExpression(
            '/d-flex align-items-center justify-content-between gap-3/',
            $componentSource,
            'The value/icon row must keep an explicit gap so the value column never touches the icon.'
        );
    }

    /**
     * E4-D2 (F-04): карточка — контейнер размера; при узкой ширине иконка
     * уменьшается, а затем скрывается, а колонка текста может сжиматься
     * (min-width:0). Без этого метка/сумма наезжали на иконку на 768-1300px
     * в 3-4-колоночных раскладках Manager/Admin.
     */
    public function test_stat_card_adapts_to_its_own_width_instead_of_overlapping_the_icon(): void
    {
        $componentSource = file_get_contents(resource_path('views/cabinet/components/stat-card.blade.php'));
        $css = file_get_contents(public_path('css/cabinet-e3.css'));
        $this->assertNotFalse($componentSource);
        $this->assertNotFalse($css);

        $this->assertStringContainsString('class="stat-card__body"', $componentSource);
        $this->assertStringContainsString('class="stat-card__label"', $componentSource);

        $this->assertMatchesRegularExpression('/\.stat-card\s*\{[^}]*container:\s*stat-card\s*\/\s*inline-size;/s', $css);
        $this->assertMatchesRegularExpression('/\.stat-card__body\s*\{[^}]*min-width:\s*0;/s', $css);
        $this->assertMatchesRegularExpression(
            '/@container stat-card \(max-width:[^)]*\)\s*\{[^@]*\.stat-card__icon\s*\{\s*display:\s*none;/s',
            $css,
            'A very narrow stat-card must hide the decorative icon instead of overlapping it with the text.'
        );
    }

    /**
     * E4-D2 (F-06): кнопки фильтров админских списков больше не w-100 в узкой
     * col-md-2 — блок кнопок переносится, «Сбросить» не выходит за карточку.
     */
    public function test_admin_filter_rows_wrap_their_action_buttons(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);

        foreach ([route('cabinet.admin.bookings'), route('cabinet.admin.users')] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('admin-filter-actions', $html, $url);
            $this->assertStringContainsString('Сбросить', $html, $url);
            $this->assertDoesNotMatchRegularExpression(
                '/class="btn btn-outline-secondary w-100"[^>]*>\s*<i class="bi bi-x-circle">/',
                $html,
                "Reset button on {$url} must not be forced to w-100 inside a narrow column."
            );
        }

        $css = file_get_contents(public_path('css/cabinet-e3.css'));
        $this->assertNotFalse($css);
        $this->assertMatchesRegularExpression('/\.admin-filter-actions\s*\{[^}]*flex-wrap:\s*wrap;/s', $css);
    }

    /**
     * E4-D2 (F-07): длинный email в сводке профиля должен переноситься, а не
     * выталкивать карточку и страницу (Manager и Admin делят один паттерн).
     */
    public function test_profile_email_can_break_inside_its_card(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);

        $html = $this->actingAs($admin)->get(route('cabinet.admin.profile'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<span class="fw-bold text-break[^"]*">\s*' . preg_quote($admin->email, '/') . '\s*<\/span>/',
            $html
        );
    }

    private function extractMoneyModifierRuleBody(): string
    {
        $css = file_get_contents(public_path('css/cabinet-e3.css'));
        $this->assertNotFalse($css);

        preg_match('/\.admin-stat-value--money\s*\{([^}]*)\}/s', $css, $matches);
        $this->assertNotEmpty($matches, 'Could not locate the .admin-stat-value--money rule block.');

        return $matches[1];
    }

    public function test_manager_and_tourist_stat_card_callers_never_receive_the_admin_money_modifier(): void
    {
        $managerViews = glob(resource_path('views/manager/*.blade.php'));
        $touristViews = glob(resource_path('views/tourist/*.blade.php'));

        foreach (array_merge($managerViews ?: [], $touristViews ?: []) as $view) {
            $this->assertStringNotContainsString(
                'admin-stat-value--money',
                file_get_contents($view),
                basename($view) . ' must not opt into the Admin-only money modifier.'
            );
        }
    }

    // ------------------------------------------------------------------
    // C. Назначение ответственного — единый источник правды
    // ------------------------------------------------------------------

    public function test_assignment_dropdown_lists_only_assignable_users(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);
        $inactiveManager = $this->createUserWithRoles([Role::MANAGER]);
        $inactiveManager->is_active = false;
        $inactiveManager->save();

        $this->createBooking($this->baseBookingAttributes($tourist->id, null, Booking::STATUS_NEW));

        $html = $this->actingAs($admin)->get(route('cabinet.admin.bookings'))->assertOk()->getContent();

        // Менеджер и сам администратор — оба допустимые ответственные.
        $this->assertStringContainsString('<option value="' . $manager->id . '"', $html);
        $this->assertStringContainsString('<option value="' . $admin->id . '"', $html);
        // Турист и неактивный менеджер никогда не должны появляться в списке
        // кандидатов на назначение (турист при этом легитимно виден как клиент
        // заявки — поэтому проверяем именно вариант <option>, а не всю страницу).
        $this->assertStringNotContainsString('<option value="' . $tourist->id . '"', $html);
        $this->assertStringNotContainsString('<option value="' . $inactiveManager->id . '"', $html);
    }

    public function test_booking_currently_assigned_to_a_now_inactive_manager_still_shows_that_assignee(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);

        $booking = $this->createBooking($this->baseBookingAttributes($tourist->id, $manager->id, Booking::STATUS_PROGRESS));

        // Ответственный увольняется/деактивируется уже после назначения.
        $manager->is_active = false;
        $manager->save();

        $html = $this->actingAs($admin)->get(route('cabinet.admin.bookings'))->assertOk()->getContent();

        // Текущий (уже неактивный) ответственный по-прежнему виден в select этой
        // заявки — но как выбранный и disabled, чтобы его нельзя было выбрать заново.
        $this->assertMatchesRegularExpression(
            '/<option value="' . $manager->id . '" selected disabled>\s*' . preg_quote($manager->name, '/') . '\s*\(неактивен\)\s*<\/option>/',
            $html
        );
        // Появляется ровно один раз — как disabled fallback, а не ещё и как
        // обычный (активный, выбираемый) кандидат из списка $managers.
        $this->assertSame(1, substr_count($html, 'value="' . $manager->id . '"'));
    }

    public function test_inactive_assignee_presentation_does_not_grow_query_count_with_booking_count(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);

        $managerOne = $this->createUserWithRoles([Role::MANAGER]);
        $managerOne->is_active = false;
        $managerOne->save();
        $this->createBooking($this->baseBookingAttributes($tourist->id, $managerOne->id, Booking::STATUS_PROGRESS));

        $countForOneInactiveAssignee = $this->captureBookingsUsersQueryCount($admin);

        for ($i = 0; $i < 5; $i++) {
            $manager = $this->createUserWithRoles([Role::MANAGER]);
            $manager->is_active = false;
            $manager->save();
            $this->createBooking($this->baseBookingAttributes($tourist->id, $manager->id, Booking::STATUS_PROGRESS));
        }

        $countForSixInactiveAssignees = $this->captureBookingsUsersQueryCount($admin);

        // relation manager() уже была eager-loaded общим запросом до этого
        // изменения — presentation-фикс не должен добавить точечный запрос
        // на каждого неактивного ответственного.
        $this->assertSame($countForOneInactiveAssignee, $countForSixInactiveAssignees);
    }

    private function captureBookingsUsersQueryCount(User $admin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->actingAs($admin)->get(route('cabinet.admin.bookings'))->assertOk();
            $queryLog = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        return collect($queryLog)
            ->filter(fn (array $entry): bool => (bool) preg_match('/from\s+[`"]users[`"]/i', $entry['query']))
            ->count();
    }

    public function test_admin_label_on_bookings_page_does_not_grow_role_query_count_with_booking_count(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);
        $this->createUserWithRoles([Role::MANAGER, Role::ADMIN]);

        $this->createBooking($this->baseBookingAttributes($tourist->id, null, Booking::STATUS_NEW));

        $countForOneBooking = $this->captureBookingsRolesQueryCount($admin);

        for ($i = 0; $i < 5; $i++) {
            $this->createBooking($this->baseBookingAttributes($tourist->id, null, Booking::STATUS_NEW));
        }

        $countForSixBookings = $this->captureBookingsRolesQueryCount($admin);

        // "(Админ)" в фильтре и в каждой строке-дропдауне читается из уже
        // eager-loaded $managers->roles, а не через User::hasRole() —
        // иначе запросы к roles/role_user росли бы вместе с числом заявок
        // на странице (заявки × кандидаты в каждом select).
        $this->assertSame($countForOneBooking, $countForSixBookings);
    }

    private function captureBookingsRolesQueryCount(User $admin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->actingAs($admin)->get(route('cabinet.admin.bookings'))->assertOk();
            $queryLog = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        return collect($queryLog)
            ->filter(fn (array $entry): bool => (bool) preg_match('/from\s+[`"](roles|role_user)[`"]/i', $entry['query']))
            ->count();
    }

    public function test_unassigned_booking_queue_counts_and_filter_are_consistent(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);

        $unassigned = $this->createBooking($this->baseBookingAttributes($tourist->id, null, Booking::STATUS_NEW));
        $assigned = $this->createBooking($this->baseBookingAttributes($tourist->id, $manager->id, Booking::STATUS_PROGRESS));

        $html = $this->actingAs($admin)->get(route('cabinet.admin.bookings'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Не назначено:\s*1/', $html);
        $this->assertMatchesRegularExpression('/Новые:\s*1/', $html);
        $this->assertMatchesRegularExpression('/В обработке:\s*1/', $html);

        $filtered = $this->actingAs($admin)
            ->get(route('cabinet.admin.bookings', ['manager' => 'unassigned']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('#' . $unassigned->id, $filtered);
        $this->assertStringNotContainsString('#' . $assigned->id, $filtered);
    }

    public function test_bookings_list_renders_honest_empty_state(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);

        $html = $this->actingAs($admin)
            ->get(route('cabinet.admin.bookings', ['search' => 'no-such-booking-xyz']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Заявки не найдены.', $html);
    }

    // ------------------------------------------------------------------
    // C.1 Направление — заявка без tour_id остаётся опознаваемой (E3-A5)
    // ------------------------------------------------------------------

    /**
     * Регрессия на browser-QA дефект: заявки без tour_id (в т.ч. созданные
     * вручную) показывали "Без названия" в столбце "Тур", потому что
     * `tour_name` — несуществующий на модели/в схеме атрибут (в таблице
     * bookings такой колонки нет). Реальные destination_country/city у
     * заявки при этом были и остаются доступны без дополнительных запросов.
     */
    public function test_admin_bookings_show_destination_direction_for_booking_without_linked_tour(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $touristOne = $this->createUserWithRoles([Role::TOURIST]);
        $touristTwo = $this->createUserWithRoles([Role::TOURIST]);

        $turkeyBooking = $this->createBooking(array_merge(
            $this->baseBookingAttributes($touristOne->id, $manager->id, Booking::STATUS_PROGRESS),
            ['tour_id' => null, 'destination_country' => 'Турция', 'destination_city' => 'Анталья']
        ));
        $egyptBooking = $this->createBooking(array_merge(
            $this->baseBookingAttributes($touristTwo->id, $manager->id, Booking::STATUS_PROGRESS),
            ['tour_id' => null, 'destination_country' => 'Египет', 'destination_city' => 'Шарм-эль-Шейх']
        ));

        $html = $this->actingAs($admin)->get(route('cabinet.admin.bookings'))->assertOk()->getContent();

        $turkeyRow = $this->extractBookingRow($html, $turkeyBooking->id);
        $this->assertStringContainsString('Турция', $turkeyRow);
        $this->assertStringContainsString('Анталья', $turkeyRow);
        $this->assertStringNotContainsString('Без названия', $turkeyRow);

        // Второе направление должно оставаться отличимым от первого в своей
        // собственной строке — это не одна и та же наугад совпавшая подстрока.
        $egyptRow = $this->extractBookingRow($html, $egyptBooking->id);
        $this->assertStringContainsString('Египет', $egyptRow);
        $this->assertStringContainsString('Шарм-эль-Шейх', $egyptRow);
        $this->assertStringNotContainsString('Без названия', $egyptRow);
        $this->assertStringNotContainsString('Турция', $egyptRow);
    }

    /**
     * Если у заявки нет собственных destination-полей (пустая строка —
     * единственный способ обойти NOT NULL destination_country в схеме), но
     * есть связанный Tour, столбец должен опираться на реально существующие
     * данные тура, а не показывать "Без названия"/выдуманную заглушку.
     */
    public function test_admin_bookings_fall_back_to_linked_tour_title_when_destination_fields_are_empty(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);

        $tour = Tour::factory()->create(['title' => 'Дубай Люкс 5 звёзд']);

        $booking = $this->createBooking(array_merge(
            $this->baseBookingAttributes($tourist->id, $manager->id, Booking::STATUS_PROGRESS),
            ['tour_id' => $tour->id, 'destination_country' => '', 'destination_city' => null]
        ));

        $html = $this->actingAs($admin)->get(route('cabinet.admin.bookings'))->assertOk()->getContent();
        $row = $this->extractBookingRow($html, $booking->id);

        $this->assertStringContainsString('Дубай Люкс 5 звёзд', $row);
        $this->assertStringNotContainsString('Без названия', $row);
    }

    /**
     * Заявка без destination-полей и без связанного тура — единственный
     * случай, где допустима явная честная заглушка (не выдуманные данные).
     */
    public function test_admin_bookings_show_honest_placeholder_when_no_direction_data_exists_at_all(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);

        $booking = $this->createBooking(array_merge(
            $this->baseBookingAttributes($tourist->id, $manager->id, Booking::STATUS_PROGRESS),
            ['tour_id' => null, 'destination_country' => '', 'destination_city' => null]
        ));

        $html = $this->actingAs($admin)->get(route('cabinet.admin.bookings'))->assertOk()->getContent();
        $row = $this->extractBookingRow($html, $booking->id);

        $this->assertStringContainsString('Не указано', $row);
        $this->assertStringNotContainsString('Без названия', $row);
    }

    private function extractBookingRow(string $html, int $bookingId): string
    {
        preg_match(
            '/<tr>\s*<td><strong>#' . $bookingId . '<\/strong><\/td>[\s\S]*?<\/tr>/',
            $html,
            $matches
        );
        $this->assertNotEmpty($matches, "Could not locate the table row for booking #{$bookingId}.");

        return $matches[0];
    }

    // ------------------------------------------------------------------
    // D. Финансы — честные метки и отсутствие N+1 по менеджерам
    // ------------------------------------------------------------------

    public function test_finance_labels_match_their_computation(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);

        $this->createBooking($this->baseBookingAttributes($tourist->id, $manager->id, Booking::STATUS_COMPLETED));

        $html = $this->actingAs($admin)->get(route('cabinet.admin.finance'))->assertOk()->getContent();

        // 100 000 ₽ завершённая заявка — выручка завершённых заявок.
        $this->assertStringContainsString('100 000', $html);
        // 40 000 ₽ оплачено — отражено в "Оплачено всего".
        $this->assertStringContainsString('40 000', $html);
        $this->assertStringNotContainsString('Прибыль', $html);
        $this->assertStringNotContainsString('Комиss', $html);
    }

    /**
     * Регрессия на browser-QA дефект: отменённая заявка (booking #422 в QA,
     * total_price=99 000, paid_amount=0) попадала в "Задолженность", хотя
     * отменённая заявка не формирует долг перед компанией. "Оплачено
     * всего" при этом должно остаться суммой по ВСЕМ заявкам — реально
     * полученные деньги не перестают быть полученными из-за отмены.
     */
    public function test_finance_outstanding_excludes_cancelled_bookings(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $touristOne = $this->createUserWithRoles([Role::TOURIST]);
        $touristTwo = $this->createUserWithRoles([Role::TOURIST]);

        // Подтверждённая заявка по-прежнему должна числиться в долге.
        $this->createBooking(array_merge(
            $this->baseBookingAttributes($touristOne->id, $manager->id, Booking::STATUS_CONFIRMED),
            ['total_price' => 250000, 'paid_amount' => 100000]
        ));

        // Отменённая и неоплаченная — не должна попадать в задолженность.
        $this->createBooking(array_merge(
            $this->baseBookingAttributes($touristTwo->id, $manager->id, Booking::STATUS_CANCELLED),
            ['total_price' => 99000, 'paid_amount' => 0]
        ));

        $html = $this->actingAs($admin)->get(route('cabinet.admin.finance'))->assertOk()->getContent();

        // 250 000 - 100 000 = 150 000. Если бы отменённая заявка всё ещё
        // учитывалась, было бы 249 000.
        $this->assertSame('150 000 ₽', $this->extractStatCardValue($html, 'Задолженность'));
        $this->assertSame('100 000 ₽', $this->extractStatCardValue($html, 'Оплачено всего'));
    }

    /**
     * Регрессия на browser-QA дефект: разбор "Выручка по менеджерам"
     * фильтровал только роль manager, поэтому завершённая заявка,
     * закреплённая за администратором, пропадала из построчного разбора,
     * хотя верхняя карточка "Выручка (завершено)" её честно учитывала —
     * сумма строк расходилась с итогом. Числа ниже — реальный QA-фикстур
     * из отчёта (185 000 + 1 248 750 + 300 000 = 1 733 750).
     */
    public function test_finance_responsible_employee_breakdown_includes_admin_assignments_and_reconciles_with_total(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $responsibleAdmin = $this->createUserWithRoles([Role::ADMIN]);
        $managerOne = $this->createUserWithRoles([Role::MANAGER]);
        $managerTwo = $this->createUserWithRoles([Role::MANAGER]);
        $inactiveManager = $this->createUserWithRoles([Role::MANAGER]);
        $inactiveManager->is_active = false;
        $inactiveManager->save();

        $touristA = $this->createUserWithRoles([Role::TOURIST]);
        $touristB = $this->createUserWithRoles([Role::TOURIST]);
        $touristC = $this->createUserWithRoles([Role::TOURIST]);

        $this->createBooking(array_merge(
            $this->baseBookingAttributes($touristA->id, $managerOne->id, Booking::STATUS_COMPLETED),
            ['total_price' => 185000]
        ));
        $this->createBooking(array_merge(
            $this->baseBookingAttributes($touristB->id, $managerTwo->id, Booking::STATUS_COMPLETED),
            ['total_price' => 1248750]
        ));
        $this->createBooking(array_merge(
            $this->baseBookingAttributes($touristC->id, $responsibleAdmin->id, Booking::STATUS_COMPLETED),
            ['total_price' => 300000]
        ));

        $html = $this->actingAs($admin)->get(route('cabinet.admin.finance'))->assertOk()->getContent();

        $adminRow = $this->extractEmployeeRow($html, $responsibleAdmin->name);
        $this->assertStringContainsString('(Админ)', $adminRow);
        $this->assertStringContainsString('300 000 ₽', $adminRow);

        $this->assertStringContainsString('185 000 ₽', $this->extractEmployeeRow($html, $managerOne->name));
        $this->assertStringContainsString('1 248 750 ₽', $this->extractEmployeeRow($html, $managerTwo->name));

        // Неактивный, но исторически реальный сотрудник не исчезает молча —
        // честная нулевая строка, а не отсутствие в списке.
        $inactiveRow = $this->extractEmployeeRow($html, $inactiveManager->name);
        $this->assertMatchesRegularExpression('/<td>\s*0\s*<\/td>/', $inactiveRow);

        // Разбор по строкам обязан сходиться с итоговой карточкой сверху.
        $this->assertSame('1 733 750 ₽', $this->extractStatCardValue($html, 'Выручка (завершено)'));
    }

    /**
     * Регрессия на browser-QA дефект: денежное значение в разборе по
     * ответственным сотрудникам (например "1 248 750 ₽") переносилось по
     * пробелам-разделителям тысяч, из-за чего символ ₽ оказывался на
     * отдельной строке. Ячейка обязана удерживать сумму в одну строку —
     * здесь используется штатная Bootstrap-утилита text-nowrap, а не
     * собственный CSS.
     */
    public function test_finance_responsible_employee_money_cell_does_not_wrap(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);

        $this->createBooking(array_merge(
            $this->baseBookingAttributes($tourist->id, $manager->id, Booking::STATUS_COMPLETED),
            ['total_price' => 1248750]
        ));

        $html = $this->actingAs($admin)->get(route('cabinet.admin.finance'))->assertOk()->getContent();
        $row = $this->extractEmployeeRow($html, $manager->name);

        $this->assertMatchesRegularExpression(
            '/<td class="text-nowrap">1 248 750 ₽<\/td>/',
            $row,
            'The responsible-employee revenue cell must keep the sum and ₽ on one line via text-nowrap.'
        );
    }

    /**
     * Регрессия на browser-QA дефект: заголовок столбца в "Последние
     * заявки" всё ещё гласил "Менеджер", хотя тот же столбец легитимно
     * показывает и назначенных администраторов — по уже одобренному
     * контракту E3-A5 ответственным может быть Менеджер ИЛИ Админ.
     */
    public function test_finance_recent_bookings_column_uses_responsible_employee_label(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);

        $this->createBooking($this->baseBookingAttributes($tourist->id, $admin->id, Booking::STATUS_PROGRESS));

        $html = $this->actingAs($admin)->get(route('cabinet.admin.finance'))->assertOk()->getContent();

        $this->assertStringContainsString('<th>Ответственный</th>', $html);
        $this->assertStringNotContainsString('<th>Менеджер</th>', $html);
    }

    private function extractEmployeeRow(string $html, string $employeeName): string
    {
        // Blade's {{ }} HTML-escapes the name (e.g. an apostrophe becomes
        // &#039;) — factory-generated names occasionally contain one, so the
        // pattern must match the escaped form actually rendered, not the raw
        // PHP string, or this helper flakes only on those random names.
        $escapedName = htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8');

        preg_match(
            '/<tr>\s*<td>\s*' . preg_quote($escapedName, '/') . '[\s\S]*?<\/tr>/u',
            $html,
            $matches
        );
        $this->assertNotEmpty($matches, "Could not locate the responsible-employee row for \"{$employeeName}\".");

        return $matches[0];
    }

    private function extractStatCardValue(string $html, string $title): string
    {
        preg_match(
            '/' . preg_quote($title, '/') . '\s*<\/div>\s*<div class="stat-card__value[^"]*"[^>]*>\s*([^<]+?)\s*<\/div>/u',
            $html,
            $matches
        );
        $this->assertNotEmpty($matches, "Could not locate the stat-card value for \"{$title}\".");

        return trim($matches[1]);
    }

    public function test_finance_manager_query_count_does_not_grow_with_manager_count(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);

        $managerOne = $this->createUserWithRoles([Role::MANAGER]);
        $touristOne = $this->createUserWithRoles([Role::TOURIST]);
        $this->createBooking($this->baseBookingAttributes($touristOne->id, $managerOne->id, Booking::STATUS_COMPLETED));

        $countForOneManager = $this->captureBookingsTableQueryCount($admin);

        for ($i = 0; $i < 5; $i++) {
            $manager = $this->createUserWithRoles([Role::MANAGER]);
            $tourist = $this->createUserWithRoles([Role::TOURIST]);
            $this->createBooking($this->baseBookingAttributes($tourist->id, $manager->id, Booking::STATUS_COMPLETED));
        }

        $countForSixManagers = $this->captureBookingsTableQueryCount($admin);

        $this->assertSame($countForOneManager, $countForSixManagers);
    }

    private function captureBookingsTableQueryCount(User $admin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $response = $this->actingAs($admin)->get(route('cabinet.admin.finance'));
            $response->assertOk();
            $queryLog = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        return collect($queryLog)
            ->filter(fn (array $entry): bool => (bool) preg_match('/from\s+[`"]bookings[`"]/i', $entry['query']))
            ->count();
    }

    /**
     * С момента E3-A5 finance-QA-фикса ответственным по заявке может быть и
     * администратор, поэтому таблица разбора по сотрудникам больше не может
     * быть буквально пустой, пока хоть один Admin/Manager существует —
     * залогиненный администратор сам попадает в неё честной нулевой
     * строкой. "Ответственных пока нет." остаётся достижимым состоянием
     * компонента (см. forelse в blade), но не воспроизводится через этот
     * маршрут, пока учётка администратора должна пройти аутентификацию.
     */
    public function test_finance_renders_without_error_with_zero_data(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);

        $html = $this->actingAs($admin)->get(route('cabinet.admin.finance'))->assertOk()->getContent();

        $employeeRow = $this->extractEmployeeRow($html, $admin->name);
        $this->assertStringContainsString('(Админ)', $employeeRow);
        $this->assertMatchesRegularExpression('/<td>\s*0\s*<\/td>/', $employeeRow);
        $this->assertStringContainsString('0 ₽', $employeeRow);

        $this->assertStringContainsString('Заявок пока нет.', $html);
    }

    // ------------------------------------------------------------------
    // E. Наблюдатель vs назначенный администратор в чате (контракт E3-A2)
    // ------------------------------------------------------------------

    public function test_admin_observer_chat_has_no_composer_for_a_foreign_booking(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);

        $booking = $this->createBooking($this->baseBookingAttributes($tourist->id, $manager->id, Booking::STATUS_PROGRESS));
        Message::query()->create([
            'booking_id' => $booking->id,
            'sender_id' => $tourist->id,
            'receiver_id' => $manager->id,
            'message' => 'Hello',
            'is_read' => false,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-chat-composer', $html);
        $this->assertStringNotContainsString('data-chat-messages-url', $html);
    }

    public function test_admin_assigned_to_booking_gets_a_composer(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);

        $booking = $this->createBooking($this->baseBookingAttributes($tourist->id, $admin->id, Booking::STATUS_PROGRESS));

        $html = $this->actingAs($admin)
            ->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-chat-composer', $html);
        $this->assertStringContainsString('data-chat-messages-url', $html);
    }

    // ------------------------------------------------------------------
    // E.1 Список чатов Admin — направление и адаптивные бейджи (E3-A5)
    // ------------------------------------------------------------------

    /**
     * Регрессия на browser-QA дефект: карточка треда в "Все чаты" не
     * показывала направление заявки, вынуждая администратора открывать
     * тред, чтобы понять, о какой поездке речь. Два разных направления
     * должны быть различимы прямо в списке, без открытия треда.
     */
    public function test_admin_chat_thread_list_shows_distinguishable_destinations(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $touristOne = $this->createUserWithRoles([Role::TOURIST]);
        $touristTwo = $this->createUserWithRoles([Role::TOURIST]);

        $egyptBooking = $this->createBooking(array_merge(
            $this->baseBookingAttributes($touristOne->id, $manager->id, Booking::STATUS_PROGRESS),
            ['destination_country' => 'Египет', 'destination_city' => 'Шарм-эль-Шейх']
        ));
        $uaeBooking = $this->createBooking(array_merge(
            $this->baseBookingAttributes($touristTwo->id, null, Booking::STATUS_NEW),
            ['destination_country' => 'ОАЭ', 'destination_city' => 'Дубай']
        ));

        $html = $this->actingAs($admin)->get(route('cabinet.admin.chats'))->assertOk()->getContent();

        $egyptThread = $this->extractChatThread($html, $egyptBooking->id);
        $this->assertStringContainsString('Египет', $egyptThread);
        $this->assertStringContainsString('Шарм-эль-Шейх', $egyptThread);
        $this->assertStringNotContainsString('ОАЭ', $egyptThread);
        $this->assertStringContainsString(e($manager->name), $egyptThread);

        $uaeThread = $this->extractChatThread($html, $uaeBooking->id);
        $this->assertStringContainsString('ОАЭ', $uaeThread);
        $this->assertStringContainsString('Дубай', $uaeThread);
        $this->assertStringNotContainsString('Египет', $uaeThread);

        // Неназначенная заявка сохраняет текущую устоявшуюся формулировку.
        $this->assertStringContainsString('Менеджер не назначен', $uaeThread);
    }

    /**
     * Статус-бейдж и оба счётчика непрочитанных должны остаться в разметке
     * треда — это не декоративные элементы, они несут операционный смысл
     * (кто именно не прочитал последние сообщения).
     */
    public function test_admin_chat_thread_list_keeps_status_and_unread_badge_markup(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);

        $booking = $this->createBooking($this->baseBookingAttributes($tourist->id, $manager->id, Booking::STATUS_PROGRESS));

        Message::query()->create([
            'booking_id' => $booking->id,
            'sender_id' => $tourist->id,
            'receiver_id' => $manager->id,
            'message' => 'Здравствуйте',
            'is_read' => false,
        ]);
        Message::query()->create([
            'booking_id' => $booking->id,
            'sender_id' => $manager->id,
            'receiver_id' => $tourist->id,
            'message' => 'Добрый день',
            'is_read' => false,
        ]);

        $html = $this->actingAs($admin)->get(route('cabinet.admin.chats'))->assertOk()->getContent();
        $thread = $this->extractChatThread($html, $booking->id);

        $this->assertStringContainsString('status-badge', $thread);
        $this->assertStringContainsString('Менеджер: 1', $thread);
        $this->assertStringContainsString('Турист: 1', $thread);
    }

    /**
     * Регрессия на переполнение карточки треда: бейджи статуса и
     * непрочитанных раньше лежали в строке без переноса и вылезали за
     * правый край карточки. Контейнер бейджей должен явно объявлять
     * wrap-раскладку — иначе баг может вернуться незаметно для diff'а.
     */
    public function test_admin_chat_thread_badges_use_an_explicit_wrapping_container(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        $tourist = $this->createUserWithRoles([Role::TOURIST]);

        $booking = $this->createBooking($this->baseBookingAttributes($tourist->id, $manager->id, Booking::STATUS_PROGRESS));

        $html = $this->actingAs($admin)->get(route('cabinet.admin.chats'))->assertOk()->getContent();
        $thread = $this->extractChatThread($html, $booking->id);

        $this->assertMatchesRegularExpression(
            '/data-chat-thread-badges[^>]*class="[^"]*flex-wrap[^"]*"|class="[^"]*flex-wrap[^"]*"[^>]*data-chat-thread-badges/',
            $thread,
            'The badge row must carry an explicit wrap-capable layout hook (data-chat-thread-badges + flex-wrap), not a plain nowrap flex row.'
        );
    }

    private function extractChatThread(string $html, int $bookingId): string
    {
        preg_match(
            '/Заявка #' . $bookingId . '[\s\S]*?<\/a>/',
            $html,
            $matches
        );
        $this->assertNotEmpty($matches, "Could not locate the chat thread card for booking #{$bookingId}.");

        return $matches[0];
    }

    // ------------------------------------------------------------------
    // F. Список пользователей — активность и быстрая роль (E3-A5)
    // ------------------------------------------------------------------

    /**
     * Регрессия на browser-QA дефект: is_active=false нигде не отражался в
     * списке — зелёное "Да" под "Подтвержден" относится только к
     * email-верификации и не должно приниматься за активность аккаунта.
     */
    public function test_users_list_shows_explicit_inactive_indicator_for_inactive_account(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);

        $inactiveManager = $this->createUserWithRoles([Role::MANAGER]);
        $inactiveManager->is_active = false;
        $inactiveManager->save();

        $activeManager = $this->createUserWithRoles([Role::MANAGER]);

        $html = $this->actingAs($admin)->get(route('cabinet.admin.users'))->assertOk()->getContent();

        $inactiveRow = $this->extractUserRow($html, $inactiveManager->id);
        $this->assertStringContainsString('Неактивен', $inactiveRow);

        // Активная строка не должна получать тот же бейдж — иначе он ничего
        // не сообщает и превращается в шум.
        $activeRow = $this->extractUserRow($html, $activeManager->id);
        $this->assertStringNotContainsString('Неактивен', $activeRow);
    }

    /**
     * Активность аккаунта (is_active) и подтверждение email — независимые
     * факты. Неактивный, но уже подтвердивший почту пользователь должен
     * показывать оба состояния одновременно, а не одно вместо другого.
     */
    public function test_users_list_inactive_indicator_is_independent_of_email_verification_badge(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);

        $inactiveVerified = $this->createUserWithRoles([Role::TOURIST]);
        $inactiveVerified->is_active = false;
        $inactiveVerified->email_verified_at = now();
        $inactiveVerified->save();

        $html = $this->actingAs($admin)->get(route('cabinet.admin.users'))->assertOk()->getContent();
        $row = $this->extractUserRow($html, $inactiveVerified->id);

        $this->assertStringContainsString('Неактивен', $row);
        $this->assertMatchesRegularExpression('/bg-success">\s*Да\s*<\/span>/', $row);
    }

    /**
     * Регрессия на browser-QA дефект: "Быстрая роль" — голый
     * .form-select-sm в flex-строке — сжимался до пары пикселей, и был
     * виден только обрывок выбранного варианта ("Администратор" → "А").
     * Полные варианты должны присутствовать в разметке, а сам select —
     * нести точечный layout-хук (класс с min-width), не общий для всех
     * select в приложении.
     */
    public function test_users_list_quick_role_select_keeps_full_role_labels_and_width_hook(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $manager = $this->createUserWithRoles([Role::MANAGER]);
        // Быстрая роль строится из Role::all() — без этого пользователя роль
        // "tourist" вовсе не существует в тестовой БД, и её <option> не может
        // появиться независимо от разметки.
        $this->createUserWithRoles([Role::TOURIST]);

        $html = $this->actingAs($admin)->get(route('cabinet.admin.users'))->assertOk()->getContent();
        $row = $this->extractUserRow($html, $manager->id);

        $this->assertStringContainsString('admin-quick-role-select', $row);
        $this->assertMatchesRegularExpression('/<option value="admin"[^>]*>\s*Админ\s*<\/option>/', $row);
        $this->assertMatchesRegularExpression('/<option value="manager"[^>]*>\s*Менеджер\s*<\/option>/', $row);
        $this->assertMatchesRegularExpression('/<option value="tourist"[^>]*>\s*Турист\s*<\/option>/', $row);
    }

    /**
     * Класс — не просто имя без эффекта: правило должно реально задавать
     * min-width (не фиксированный width, чтобы не создавать переполнение).
     */
    public function test_admin_quick_role_select_css_uses_min_width_not_fixed_width(): void
    {
        $css = file_get_contents(public_path('css/cabinet-e3.css'));
        $this->assertNotFalse($css);

        preg_match('/\.admin-quick-role-select\s*\{([^}]*)\}/s', $css, $matches);
        $this->assertNotEmpty($matches, 'Could not locate the .admin-quick-role-select rule block.');

        $ruleBody = $matches[1];
        $this->assertMatchesRegularExpression('/min-width:\s*\d/', $ruleBody);
        $this->assertDoesNotMatchRegularExpression('/(?<!min-)width:\s*\d/', $ruleBody);
    }

    // ------------------------------------------------------------------
    // G. Управление ролями — локализованный текст подтверждения (E3-A5)
    // ------------------------------------------------------------------

    /**
     * Регрессия на browser-QA дефект: confirm() на удаление роли показывал
     * внутренний слаг ("manager"), а не читаемую русскую метку. Слаг в
     * запрос при этом не передаётся вовсе — маршрут удаления адресует роль
     * по $role->id в URL, поэтому это чисто отображение.
     */
    public function test_remove_role_confirmation_uses_localized_role_label_not_slug(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);

        $managerUser = $this->createUserWithRoles([Role::MANAGER]);
        $adminUser = $this->createUserWithRoles([Role::ADMIN]);
        $touristUser = $this->createUserWithRoles([Role::TOURIST]);

        $managerRoleId = $managerUser->roles()->where('name', Role::MANAGER)->firstOrFail()->id;
        $adminRoleId = $adminUser->roles()->where('name', Role::ADMIN)->firstOrFail()->id;
        $touristRoleId = $touristUser->roles()->where('name', Role::TOURIST)->firstOrFail()->id;

        $managerHtml = $this->actingAs($admin)
            ->get(route('cabinet.admin.user-roles', $managerUser->id))->assertOk()->getContent();
        $this->assertStringContainsString('Удалить роль «Менеджер» у этого пользователя?', $managerHtml);
        $this->assertStringNotContainsString('Удалить роль «manager»', $managerHtml);
        // Слаг остаётся в самом маршруте (адресация по id), а не в тексте.
        $this->assertStringContainsString(
            route('cabinet.admin.remove-role', [$managerUser->id, $managerRoleId]),
            $managerHtml
        );

        $adminHtml = $this->actingAs($admin)
            ->get(route('cabinet.admin.user-roles', $adminUser->id))->assertOk()->getContent();
        $this->assertStringContainsString('Удалить роль «Администратор» у этого пользователя?', $adminHtml);
        $this->assertStringNotContainsString('Удалить роль «admin»', $adminHtml);
        $this->assertStringContainsString(
            route('cabinet.admin.remove-role', [$adminUser->id, $adminRoleId]),
            $adminHtml
        );

        $touristHtml = $this->actingAs($admin)
            ->get(route('cabinet.admin.user-roles', $touristUser->id))->assertOk()->getContent();
        $this->assertStringContainsString('Удалить роль «Турист» у этого пользователя?', $touristHtml);
        $this->assertStringNotContainsString('Удалить роль «tourist»', $touristHtml);
        $this->assertStringContainsString(
            route('cabinet.admin.remove-role', [$touristUser->id, $touristRoleId]),
            $touristHtml
        );
    }

    /**
     * Сама логика удаления роли не затронута этой правкой: подтверждение —
     * только клиентский confirm(), запрос всё ещё бьёт по тому же DELETE
     * маршруту и реально снимает роль после подтверждения в браузере
     * (которое здесь не участвует — тестируем прямой HTTP-запрос формы).
     */
    public function test_remove_role_still_removes_the_role_after_confirmation_copy_change(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);
        $managerUser = $this->createUserWithRoles([Role::MANAGER]);
        $roleId = $managerUser->roles()->where('name', Role::MANAGER)->firstOrFail()->id;

        $this->actingAs($admin)
            ->delete(route('cabinet.admin.remove-role', [$managerUser->id, $roleId]))
            ->assertRedirect();

        $this->assertFalse($managerUser->fresh()->hasRole(Role::MANAGER));
    }

    private function extractUserRow(string $html, int $userId): string
    {
        preg_match(
            '/<tr>\s*<td>' . $userId . '<\/td>[\s\S]*?<\/tr>/',
            $html,
            $matches
        );
        $this->assertNotEmpty($matches, "Could not locate the users-list row for user #{$userId}.");

        return $matches[0];
    }

    // ------------------------------------------------------------------
    // H. Профиль администратора — порядок карточек на мобильной (E3-A5)
    // ------------------------------------------------------------------

    /**
     * Регрессия на browser-QA дефект: страница строилась на паре Bootstrap
     * .col-md-8/.col-md-4, и при стекировании на мобильной (<768px) весь
     * левый столбец (включая деструктивную карточку "Удаление аккаунта")
     * рендерился раньше правого столбца (аватар/идентити-карточка). Фикс
     * переводит все шесть карточек в прямые дети одного CSS Grid-контейнера
     * с именованными grid-area, поэтому реальный DOM-порядок сам совпадает
     * с целевым порядком отображения (что важно и для порядка чтения
     * скринридером, не только для визуальной раскладки через CSS).
     * PHPUnit не может измерить реальную ширину/раскладку в браузере —
     * здесь проверяется структурный контракт (DOM-порядок карточек и
     * наличие grid-area хуков в разметке/CSS), а не итоговый визуальный
     * рендер в конкретном viewport.
     */
    public function test_admin_profile_cards_appear_in_the_target_dom_order(): void
    {
        $admin = $this->createUserWithRoles([Role::ADMIN]);

        $html = $this->actingAs($admin)->get(route('cabinet.admin.profile'))->assertOk()->getContent();

        $accountPos = strpos($html, 'admin-profile-layout__account');
        $avatarPos = strpos($html, 'admin-profile-layout__avatar');
        $infoPos = strpos($html, 'admin-profile-layout__info');
        $securityPos = strpos($html, 'admin-profile-layout__security');
        $notificationsPos = strpos($html, 'admin-profile-layout__notifications');
        $deletePos = strpos($html, 'admin-profile-layout__delete');

        foreach ([
            'account' => $accountPos,
            'avatar' => $avatarPos,
            'info' => $infoPos,
            'security' => $securityPos,
            'notifications' => $notificationsPos,
            'delete' => $deletePos,
        ] as $name => $pos) {
            $this->assertNotFalse($pos, "Could not locate the \"{$name}\" card layout hook in the rendered profile page.");
        }

        $this->assertTrue(
            $accountPos < $avatarPos
            && $avatarPos < $infoPos
            && $infoPos < $securityPos
            && $securityPos < $notificationsPos
            && $notificationsPos < $deletePos,
            'Admin profile cards must appear in the DOM in the order: account, avatar, info, security, '
            . 'notifications, delete — the destructive "Удаление аккаунта" card must be last, and the identity '
            . '(avatar) / account-info cards must come right after the account-edit form, not after security.'
        );
    }

    /**
     * Смежный структурный контракт: карточки должны быть прямыми детьми
     * одного .admin-profile-layout грид-контейнера (а не разложены по паре
     * .col-md-8/.col-md-4), иначе именованные grid-area в CSS не смогут
     * переставить их независимо от исходной колонки.
     */
    public function test_admin_profile_no_longer_uses_bootstrap_two_column_wrappers(): void
    {
        $source = file_get_contents(resource_path('views/admin/profile.blade.php'));
        $this->assertNotFalse($source);

        $this->assertStringContainsString('admin-profile-layout', $source);
        $this->assertDoesNotMatchRegularExpression(
            '/class="col-md-8"|class="col-md-4"/',
            $source,
            'The profile page must not group cards into Bootstrap .col-md-8/.col-md-4 wrapper columns — that '
            . 'structure makes the destructive delete-account card render before the identity/avatar card on '
            . 'mobile, since Bootstrap stacks the entire left column before the entire right column.'
        );
    }

    /**
     * CSS-контракт: каждый именованный grid-area должен реально существовать
     * в правиле .admin-profile-layout как для мобильной (одна колонка,
     * целевой порядок), так и для десктопной (min-width: 768px, сохранённая
     * двухколоночная композиция) раскладки.
     */
    public function test_admin_profile_layout_css_defines_target_mobile_order_and_preserves_desktop_columns(): void
    {
        $css = file_get_contents(public_path('css/cabinet-e3.css'));
        $this->assertNotFalse($css);

        preg_match(
            '/\.admin-profile-layout\s*\{[^}]*grid-template-areas:\s*([^;]+);/s',
            $css,
            $mobileMatches
        );
        $this->assertNotEmpty($mobileMatches, 'Could not locate the base (mobile) .admin-profile-layout grid-template-areas rule.');

        $mobileOrder = [];
        preg_match_all('/"([a-z]+)"/', $mobileMatches[1], $mobileAreaMatches);
        $mobileOrder = $mobileAreaMatches[1];

        $this->assertSame(
            ['account', 'avatar', 'info', 'security', 'notifications', 'delete'],
            $mobileOrder,
            'The base (mobile-first) grid-template-areas must list the target order with the destructive '
            . '"delete" area last.'
        );

        preg_match(
            '/@media \(min-width:\s*768px\)\s*\{\s*\.admin-profile-layout\s*\{[^}]*grid-template-areas:\s*([^;]+);/s',
            $css,
            $desktopMatches
        );
        $this->assertNotEmpty($desktopMatches, 'Could not locate the desktop (min-width: 768px) .admin-profile-layout override.');

        // Двухколоночная композиция сохранена: "avatar" и "info" делят одну
        // строку с "account"/"security" соответственно, а не переносятся под
        // весь левый столбец.
        $this->assertMatchesRegularExpression('/"account\s+avatar"/', $desktopMatches[1]);
        $this->assertMatchesRegularExpression('/"security\s+avatar"/', $desktopMatches[1]);
        $this->assertMatchesRegularExpression('/"notifications\s+info"/', $desktopMatches[1]);
        $this->assertMatchesRegularExpression('/"delete\s+info"/', $desktopMatches[1]);
    }
}
