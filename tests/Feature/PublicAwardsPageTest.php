<?php

namespace Tests\Feature;

use App\Models\Award;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * E1-FINAL-09: публичная страница "Наши достижения" (awards.index) должна вести
 * себя предсказуемо на граничных данных: пустой список, строка без картинки,
 * экранирование категории, отсутствие мягко удалённых строк.
 */
class PublicAwardsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_zero_awards_renders_200_with_useful_empty_state(): void
    {
        $response = $this->get(route('awards.index'));

        $response->assertOk();
        $response->assertSee('Награды пока не добавлены', false);
    }

    public function test_award_with_image_renders_the_image(): void
    {
        Award::create([
            'image' => 'https://example.com/award-1.jpg',
            'category' => 'Диплом',
        ]);
        Cache::flush();

        $response = $this->get(route('awards.index'));

        $response->assertOk();
        $response->assertSee('https://example.com/award-1.jpg', false);
        $response->assertDontSee('Награды пока не добавлены', false);
    }

    public function test_award_with_null_image_does_not_emit_bare_src(): void
    {
        Award::create([
            'image' => null,
            'category' => 'Сертификат без картинки',
        ]);
        Cache::flush();

        $response = $this->get(route('awards.index'));

        $response->assertOk();
        $this->assertStringNotContainsString('src=""', $response->getContent());
    }

    public function test_award_category_output_is_escaped(): void
    {
        Award::create([
            'image' => 'https://example.com/award-x.jpg',
            'category' => '<script>alert(1)</script>Диплом',
        ]);
        Cache::flush();

        $response = $this->get(route('awards.index'));

        $response->assertOk();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_soft_deleted_award_is_not_publicly_shown(): void
    {
        $award = Award::create([
            'image' => 'https://example.com/award-deleted.jpg',
            'category' => 'Deleted Award Marker',
        ]);
        $visible = Award::create([
            'image' => 'https://example.com/award-visible.jpg',
            'category' => 'Visible Award Marker',
        ]);
        $award->delete();
        Cache::flush();

        $response = $this->get(route('awards.index'));

        $response->assertOk();
        $response->assertSee('https://example.com/award-visible.jpg', false);
        $response->assertDontSee('https://example.com/award-deleted.jpg', false);
    }
}
