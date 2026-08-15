<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Stage 13: static source-contract test for public/js/cookie-consent.js.
 *
 * Это стандартный PHPUnit feature-тест, а не JS-тест — он читает исходник
 * public/js/cookie-consent.js как текст и проверяет узкий контракт клиентского
 * поведения при сохранении выбора cookie-согласия: обычные выборы сохраняют
 * cookie и скрывают баннер без перезагрузки страницы, а отзыв уже действующего
 * v1_all в пользу v1_necessary — единственный случай, когда страница
 * перезагружается. Серверные тесты гейтинга аналитики (PublicCookieConsentTest,
 * CacheResponseConsentIsolationTest) не дублируются здесь.
 */
class PublicCookieConsentClientBehaviorTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(public_path('js/cookie-consent.js'));
    }

    // -----------------------------------------------------------------------
    // A. Both approved consent values are still known to the client
    // -----------------------------------------------------------------------

    public function test_client_still_knows_both_approved_consent_values(): void
    {
        $source = $this->source();

        $this->assertStringContainsString("'v1_all'", $source);
        $this->assertStringContainsString("'v1_necessary'", $source);
    }

    // -----------------------------------------------------------------------
    // B. Previous consent is captured before the new cookie is written
    // -----------------------------------------------------------------------

    public function test_previous_consent_is_read_before_the_new_cookie_is_written(): void
    {
        $source = $this->source();

        $previousReadPosition = strpos($source, 'readCookie(COOKIE_NAME)', strpos($source, 'function saveChoice'));
        $writePosition = strpos($source, 'writeConsentCookie(value)', strpos($source, 'function saveChoice'));

        $this->assertIsInt($previousReadPosition, 'Expected saveChoice() to read the previous consent cookie.');
        $this->assertIsInt($writePosition, 'Expected saveChoice() to write the new consent cookie.');
        $this->assertLessThan(
            $writePosition,
            $previousReadPosition,
            'Previous consent must be captured before the new cookie value is written, otherwise the prior state is lost.'
        );
    }

    // -----------------------------------------------------------------------
    // C. Reload is guarded specifically by v1_all -> v1_necessary
    // -----------------------------------------------------------------------

    public function test_reload_is_guarded_by_the_v1_all_to_v1_necessary_transition(): void
    {
        $source = $this->source();

        $reloadPosition = strpos($source, 'window.location.reload()');
        $this->assertIsInt($reloadPosition, 'Expected exactly one reload call to remain in the client script.');

        // Ровно один вызов reload() во всём файле.
        $this->assertSame(1, substr_count($source, 'window.location.reload()'));

        $precedingSource = substr($source, 0, $reloadPosition);
        $guardPosition = strrpos($precedingSource, 'if (');
        $this->assertIsInt($guardPosition, 'Expected reload() to be guarded by a conditional.');

        $guardCondition = substr($source, $guardPosition, $reloadPosition - $guardPosition);

        $this->assertStringContainsString('previousConsent', $guardCondition);
        $this->assertStringContainsString("'v1_all'", $guardCondition);
        $this->assertStringContainsString('value', $guardCondition);
        $this->assertStringContainsString("'v1_necessary'", $guardCondition);
    }

    // -----------------------------------------------------------------------
    // D. No unconditional reload immediately after every cookie write
    // -----------------------------------------------------------------------

    public function test_there_is_no_unconditional_reload_immediately_after_every_cookie_write(): void
    {
        $source = $this->source();

        // Регрессия на прежнее поведение: функция, которая писала cookie и
        // безусловно перезагружала страницу сразу после этого, должна остаться в прошлом.
        $this->assertStringNotContainsString('saveChoiceAndReload', $source);

        $saveChoicePosition = strpos($source, 'function saveChoice(');
        $this->assertIsInt($saveChoicePosition);

        $nextFunctionPosition = strpos($source, 'document.addEventListener(\'DOMContentLoaded\'');
        $this->assertIsInt($nextFunctionPosition);

        $saveChoiceBody = substr($source, $saveChoicePosition, $nextFunctionPosition - $saveChoicePosition);

        $writePosition = strpos($saveChoiceBody, 'writeConsentCookie(value)');
        $reloadPosition = strpos($saveChoiceBody, 'window.location.reload()');
        $ifGuardPosition = strpos($saveChoiceBody, 'if (');

        $this->assertIsInt($writePosition);
        $this->assertIsInt($reloadPosition);
        $this->assertIsInt($ifGuardPosition);

        // reload() должен идти после write и после начала if-условия (то есть
        // он не выполняется безусловно сразу вслед за записью cookie).
        $this->assertGreaterThan($writePosition, $ifGuardPosition);
        $this->assertGreaterThan($ifGuardPosition, $reloadPosition);
    }

    // -----------------------------------------------------------------------
    // E. The non-reload path hides the banner
    // -----------------------------------------------------------------------

    public function test_non_reload_path_hides_the_banner(): void
    {
        $source = $this->source();

        $saveChoicePosition = strpos($source, 'function saveChoice(');
        $nextFunctionPosition = strpos($source, 'document.addEventListener(\'DOMContentLoaded\'');
        $saveChoiceBody = substr($source, $saveChoicePosition, $nextFunctionPosition - $saveChoicePosition);

        $reloadPosition = strpos($saveChoiceBody, 'window.location.reload()');
        $hidePosition = strpos($saveChoiceBody, 'banner.hidden = true');

        $this->assertIsInt($reloadPosition, 'Expected a reload branch inside saveChoice().');
        $this->assertIsInt($hidePosition, 'Expected saveChoice() to hide the banner on the non-reload path.');
        $this->assertGreaterThan(
            $reloadPosition,
            $hidePosition,
            'Banner hiding must be reached only after the reload branch has already returned, i.e. on the non-reload path.'
        );
    }

    // -----------------------------------------------------------------------
    // F. The settings action remains present and reopens the banner
    // -----------------------------------------------------------------------

    public function test_settings_action_remains_present_and_reopens_the_banner(): void
    {
        $source = $this->source();

        $this->assertStringContainsString("getElementById('cookie-settings-open')", $source);

        $settingsHandlerPosition = strpos($source, "settingsOpenBtn.addEventListener('click'");
        $this->assertIsInt($settingsHandlerPosition, 'Expected a click handler on the settings-open button.');

        $handlerBody = substr($source, $settingsHandlerPosition, 400);
        $this->assertStringContainsString('banner.hidden = false', $handlerBody);
    }
}
