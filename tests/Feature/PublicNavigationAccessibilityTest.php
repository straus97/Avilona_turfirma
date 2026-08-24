<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Правило: два выпадающих меню публичной навигации (Company / Полезная
 * информация) должны иметь уникальные id у тогглов, и каждое dropdown-menu
 * должно ссылаться через aria-labelledby ровно на свой собственный тоггл.
 * Раньше оба тоггла использовали один и тот же id="navbarDropdown", из-за
 * чего assistive technology не могла однозначно определить, какой тоггл
 * описывает какое меню — это и есть исправляемый дефект.
 *
 * Тогглы находятся структурно по стабильным id, введённым этим слайсом
 * (navbarDropdownCompany / navbarDropdownUsefulInfo), а не по видимому
 * кириллическому тексту — DOMDocument::loadHTML() в тестовом окружении не
 * даёт надёжно сопоставлять кириллицу через XPath text()/normalize-space().
 */
class PublicNavigationAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private const COMPANY_TOGGLE_ID = 'navbarDropdownCompany';
    private const USEFUL_INFO_TOGGLE_ID = 'navbarDropdownUsefulInfo';

    public function test_both_public_navigation_dropdown_toggles_render(): void
    {
        [$companyToggle, $usefulInfoToggle] = $this->getExpectedDropdownToggles();

        $this->assertNotNull($companyToggle, 'Company dropdown toggle must render');
        $this->assertNotNull($usefulInfoToggle, 'Useful information dropdown toggle must render');
        $this->assertSame('a', $companyToggle->nodeName);
        $this->assertSame('a', $usefulInfoToggle->nodeName);
        $this->assertStringContainsString('dropdown-toggle', $companyToggle->getAttribute('class'));
        $this->assertStringContainsString('dropdown-toggle', $usefulInfoToggle->getAttribute('class'));
    }

    public function test_dropdown_toggle_ids_are_non_empty_and_unique(): void
    {
        [$companyToggleId, $usefulInfoToggleId] = $this->getToggleIds();

        $this->assertNotSame('', $companyToggleId);
        $this->assertNotSame('', $usefulInfoToggleId);
        $this->assertNotSame($companyToggleId, $usefulInfoToggleId);
    }

    public function test_each_dropdown_menu_has_non_empty_aria_labelledby(): void
    {
        [$companyMenuLabelledBy, $usefulInfoMenuLabelledBy] = $this->getMenuLabelledBy();

        $this->assertNotSame('', $companyMenuLabelledBy);
        $this->assertNotSame('', $usefulInfoMenuLabelledBy);
    }

    public function test_each_menu_aria_labelledby_matches_its_own_toggle_id(): void
    {
        [$companyMenuLabelledBy, $usefulInfoMenuLabelledBy] = $this->getMenuLabelledBy();

        $this->assertSame(self::COMPANY_TOGGLE_ID, $companyMenuLabelledBy);
        $this->assertSame(self::USEFUL_INFO_TOGGLE_ID, $usefulInfoMenuLabelledBy);
    }

    public function test_each_referenced_toggle_id_resolves_to_exactly_one_element(): void
    {
        $doc = $this->renderHomepage();
        $xpath = new \DOMXPath($doc);

        $this->assertSame(1, $xpath->query(sprintf('//*[@id="%s"]', self::COMPANY_TOGGLE_ID))->length);
        $this->assertSame(1, $xpath->query(sprintf('//*[@id="%s"]', self::USEFUL_INFO_TOGGLE_ID))->length);
    }

    public function test_duplicate_dropdown_toggle_id_contract_cannot_silently_return(): void
    {
        $doc = $this->renderHomepage();
        $xpath = new \DOMXPath($doc);

        $toggles = $xpath->query('//a[contains(@class, "dropdown-toggle")]');

        $ids = [];
        foreach ($toggles as $toggle) {
            $ids[] = $toggle->getAttribute('id');
        }

        $this->assertSame(array_unique($ids), $ids, 'Public navigation dropdown toggle ids must all be unique');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @return array{0: ?\DOMElement, 1: ?\DOMElement} [companyToggle, usefulInfoToggle]
     */
    private function getExpectedDropdownToggles(): array
    {
        $doc = $this->renderHomepage();
        $xpath = new \DOMXPath($doc);

        $companyToggle = $xpath->query(sprintf(
            '//a[@id="%s" and contains(@class, "dropdown-toggle")]',
            self::COMPANY_TOGGLE_ID
        ))->item(0);

        $usefulInfoToggle = $xpath->query(sprintf(
            '//a[@id="%s" and contains(@class, "dropdown-toggle")]',
            self::USEFUL_INFO_TOGGLE_ID
        ))->item(0);

        return [$companyToggle, $usefulInfoToggle];
    }

    /**
     * @return array{0: string, 1: string} [companyToggleId, usefulInfoToggleId]
     */
    private function getToggleIds(): array
    {
        [$companyToggle, $usefulInfoToggle] = $this->getExpectedDropdownToggles();

        $this->assertNotNull($companyToggle, 'Company dropdown toggle must render');
        $this->assertNotNull($usefulInfoToggle, 'Useful information dropdown toggle must render');

        return [$companyToggle->getAttribute('id'), $usefulInfoToggle->getAttribute('id')];
    }

    /**
     * @return array{0: string, 1: string} [companyMenuLabelledBy, usefulInfoMenuLabelledBy]
     */
    private function getMenuLabelledBy(): array
    {
        $doc = $this->renderHomepage();
        $xpath = new \DOMXPath($doc);

        $companyMenu = $xpath->query(sprintf(
            '//ul[contains(@class, "dropdown-menu") and @aria-labelledby="%s"]',
            self::COMPANY_TOGGLE_ID
        ))->item(0);

        $usefulInfoMenu = $xpath->query(sprintf(
            '//ul[contains(@class, "dropdown-menu") and @aria-labelledby="%s"]',
            self::USEFUL_INFO_TOGGLE_ID
        ))->item(0);

        $this->assertNotNull($companyMenu, 'Company dropdown menu must render');
        $this->assertNotNull($usefulInfoMenu, 'Useful information dropdown menu must render');

        return [$companyMenu->getAttribute('aria-labelledby'), $usefulInfoMenu->getAttribute('aria-labelledby')];
    }

    private function renderHomepage(): \DOMDocument
    {
        $response = $this->get(route('home.index'));
        $response->assertOk();

        return $this->parseHtml($response->getContent());
    }

    private function parseHtml(string $html): \DOMDocument
    {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc;
    }
}
