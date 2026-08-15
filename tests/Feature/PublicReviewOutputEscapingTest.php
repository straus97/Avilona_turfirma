<?php

namespace Tests\Feature;

use App\Models\Reviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Правило: отзыв-контент, управляемый пользователем (name, content),
 * на главной странице (home.index) должен выводиться как экранированный
 * текст, а не как исполняемый HTML/JS. Раньше эти поля рендерились через
 * необработанный Blade-вывод {!! !!}, что позволяло опубликованному
 * отзыву внедрить произвольную разметку/скрипт на публичной странице.
 */
class PublicReviewOutputEscapingTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_escapes_malicious_review_name_and_content(): void
    {
        $maliciousName = '<script>alert("xss-name")</script>';
        $maliciousContentBody = '<img src=x onerror=alert("xss-content")>';
        // Padding pushes content past the homepage 200-character Str::limit
        // threshold, so both the shortened teaser and the hidden
        // full-content branch are rendered and exercised by this test.
        $maliciousContent = $maliciousContentBody . str_repeat('A', 220);

        Reviews::create([
            'name' => $maliciousName,
            'title' => null,
            'content' => $maliciousContent,
            'image' => null,
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('home.index'));

        $response->assertOk();

        // Raw executable markup must never appear in the response.
        $response->assertDontSee($maliciousName, false);
        $response->assertDontSee($maliciousContentBody, false);

        // The escaped equivalents must be present, proving the review is
        // rendered as text rather than silently stripped or sanitized away.
        $response->assertSee(e($maliciousName), false);
        $response->assertSee(e($maliciousContentBody), false);
    }
}
