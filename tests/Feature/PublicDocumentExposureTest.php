<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * NOV-PUBLIC-03A: граница публичных документов в public/documents.
 *
 * Классификация (см. docs/NOV-PUBLIC-03A): опубликованные юридические PDF —
 * намеренно публичны (на них ссылаются страницы); .doc/.docx — редактируемые
 * исходники тех же текстов, на них никто не ссылается, их публикация не решена
 * владельцем (N-4). Файлы не удалялись и не менялись; закрыт только прямой
 * доступ на веб-сервере (проверено вручную на Apache 2.4.69, см. документ).
 * Тест не читает содержимое документов.
 */
class PublicDocumentExposureTest extends TestCase
{
    /** Каждый .doc/.docx => PDF, на который ссылается сайт и чьим редактируемым исходником он считается. */
    private const SOURCE_TO_PUBLISHED_PDF = [
        'Памятка выезжающего за рубеж (общая).docx' => 'Reminder_for_those_traveling_abroad_general.pdf',
        'Политика в отношении защиты и обработки персональных данных.docx' => 'Policy_regarding_the_protection_and_processing_of_personal_data.pdf',
        'Пользовательское соглашение.docx' => 'User_Agreement.pdf',
        'Публичная оферта на заключение договора о реализации туристского продукта.doc' => 'Public_offer_to_conclude_an_agreement_on_the_sale_of_a_tourism_product.pdf',
        'Публичная оферта на реализацию билетов и бронирование отелей.doc' => 'Public_offer_for_ticket_sales_and_hotel_bookings.pdf',
    ];

    /** @return array<int, string> */
    private function documentFiles(): array
    {
        return array_values(array_map('basename', array_filter(
            glob(public_path('documents') . '/*') ?: [],
            'is_file'
        )));
    }

    /** @return array<string, string> путь => содержимое для всего, что может содержать ссылку на документ */
    private function linkSources(): array
    {
        $sources = [];
        foreach (['app', 'resources/views', 'routes', 'config'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match('/\.php$/', $file->getFilename()) === 1) {
                    $sources[$file->getPathname()] = (string) file_get_contents($file->getPathname());
                }
            }
        }

        return $sources;
    }

    public function test_every_file_in_public_documents_is_classified(): void
    {
        $published = array_values(self::SOURCE_TO_PUBLISHED_PDF);
        $expected = array_merge($published, array_keys(self::SOURCE_TO_PUBLISHED_PDF));
        sort($expected);

        $actual = $this->documentFiles();
        $actual = array_values(array_filter($actual, fn ($f) => $f !== '.htaccess'));
        sort($actual);

        $this->assertSame($expected, $actual, 'a new file in public/documents must be classified (public PDF or editable source) before release');
    }

    public function test_published_pdfs_exist_and_are_linked_from_the_site(): void
    {
        $views = implode("\n", array_filter($this->linkSources(), fn ($content, $path) => str_contains($path, 'views'), ARRAY_FILTER_USE_BOTH));

        foreach (self::SOURCE_TO_PUBLISHED_PDF as $pdf) {
            $this->assertFileExists(public_path('documents/' . $pdf));
            $this->assertSame('%PDF', substr((string) file_get_contents(public_path('documents/' . $pdf), false, null, 0, 4), 0, 4), "{$pdf} must be a PDF");
            $this->assertStringContainsString('documents/' . $pdf, $views, "{$pdf} must be linked from a view");
        }
    }

    public function test_no_page_links_to_an_editable_source(): void
    {
        $all = implode("\n", $this->linkSources());

        foreach (array_keys(self::SOURCE_TO_PUBLISHED_PDF) as $source) {
            $this->assertStringNotContainsString($source, $all);
            $this->assertStringNotContainsString(rawurlencode($source), $all);
        }

        $this->assertDoesNotMatchRegularExpression('#documents/[^"\'\s]+\.docx?\b#i', $all, 'no view/controller may link a .doc/.docx under /documents/');
    }

    public function test_the_documents_directory_denies_only_editable_sources_at_the_web_server(): void
    {
        $htaccess = (string) file_get_contents(public_path('documents/.htaccess'));

        $this->assertStringContainsString('<FilesMatch "(?i)\.docx?$">', $htaccess);
        $this->assertStringContainsString('Require all denied', $htaccess);
        $this->assertStringContainsString('Deny from all', $htaccess, 'Apache 2.2 fallback');
        $this->assertDoesNotMatchRegularExpression('/pdf/i', preg_replace('/^#.*$/m', '', $htaccess), 'the rule must never touch PDFs');

        // regex правила действительно совпадает с исходниками и не совпадает с PDF
        $regex = '/\.docx?$/i';
        foreach (array_keys(self::SOURCE_TO_PUBLISHED_PDF) as $source) {
            $this->assertSame(1, preg_match($regex, $source));
        }
        foreach (self::SOURCE_TO_PUBLISHED_PDF as $pdf) {
            $this->assertSame(0, preg_match($regex, $pdf));
        }
        $this->assertSame(1, preg_match($regex, 'x.DOCX'));
    }

    public function test_the_application_does_not_serve_the_documents_and_sitemap_excludes_them(): void
    {
        // Файлы отдаёт веб-сервер, а не маршруты приложения: у Laravel для /documents/* маршрута нет.
        $this->get('/documents/User_Agreement.pdf')->assertNotFound();

        $sitemap = $this->get('/sitemap.xml')->getContent();
        $this->assertStringNotContainsString('/documents/', $sitemap);

        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Disallow: /*.doc$', $robots);
        $this->assertStringContainsString('Disallow: /*.docx$', $robots);
    }

    public function test_release_archives_exclude_editable_sources_but_keep_pdfs_and_assets(): void
    {
        $attributes = (string) file_get_contents(base_path('.gitattributes'));
        foreach (['public/**/*.doc export-ignore', 'public/**/*.docx export-ignore', 'public/**/*.psd export-ignore'] as $rule) {
            $this->assertStringContainsString($rule, $attributes);
        }
        $this->assertDoesNotMatchRegularExpression('/pdf|png|jpe?g|svg|ico|css|js/i', preg_replace('/^#.*$/m', '', $attributes), 'only editable sources are denied');

        $git = trim((string) @shell_exec('git --version 2>&1'));
        if (! str_starts_with($git, 'git version')) {
            $this->markTestSkipped('git is not available');
        }

        // `git archive` — единственный механизм упаковки; атрибуты рабочего дерева учитываются.
        $list = (string) shell_exec('cd ' . escapeshellarg(base_path()) . ' && git archive --worktree-attributes HEAD public/documents public/img/special 2>&1 | tar -t 2>&1');
        if (! str_contains($list, 'public/documents/User_Agreement.pdf')) {
            $this->markTestSkipped('archive listing unavailable (tar/git)');
        }

        $this->assertDoesNotMatchRegularExpression('/\.(doc|docx|psd)\b/i', $list, 'editable sources must not be in the archive');
        foreach (self::SOURCE_TO_PUBLISHED_PDF as $pdf) {
            $this->assertStringContainsString('public/documents/' . $pdf, $list);
        }
        $this->assertStringContainsString('public/documents/.htaccess', $list);
        $this->assertStringContainsString('public/img/special/1.jpg', $list);
    }

    public function test_other_editable_sources_under_public_are_known_and_reported(): void
    {
        // Исходники изображений тоже лежат в public/ и скачиваемы; решение за владельцем (в отчёте 03A).
        // Тест фиксирует известный набор, чтобы новые «исходники» не появлялись незамеченными.
        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(public_path(), \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile() || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            if (preg_match('/\.(psd|ai|sketch|xcf|xls|xlsx|ppt|pptx|odt|rtf|sql|bak|zip|env|log)$/i', $file->getFilename()) === 1) {
                $found[] = str_replace('\\', '/', substr($file->getPathname(), strlen(public_path()) + 1));
            }
        }
        sort($found);

        $this->assertSame(['img/special/big.psd', 'img/special/little.psd'], $found);
    }
}
