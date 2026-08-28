<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Правило: если slug не передан явно, он генерируется из title через Str::slug
 * до основной валидации, и именно сгенерированный slug проверяется правилом
 * unique:articles,slug (с игнорированием текущей записи при обновлении).
 * Коллизия сгенерированного slug обязана вернуть обычную ошибку валидации
 * поля slug, а не долетать до Article::create()/update() и unique-исключения БД.
 * Это поведение должно быть одинаковым для admin и manager CMS-флоу.
 */
class ArticleGeneratedSlugConsistencyTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Create: successful generated slug
    // -----------------------------------------------------------------------

    public function test_admin_can_create_article_with_generated_unique_slug(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->post(route('cabinet.admin.articles.store'), [
            'title' => 'Уникальная статья про Италию',
            'content' => 'Содержимое статьи',
        ]);

        $response->assertRedirect(route('cabinet.admin.articles'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('articles', 1);
        $article = Article::first();
        $this->assertSame(Str::slug('Уникальная статья про Италию'), $article->slug);
    }

    public function test_manager_can_create_article_with_generated_unique_slug(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->post(route('cabinet.manager.articles.store'), [
            'title' => 'Уникальная статья про Грецию',
            'content' => 'Содержимое статьи',
        ]);

        $response->assertRedirect(route('cabinet.manager.articles'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('articles', 1);
        $article = Article::first();
        $this->assertSame(Str::slug('Уникальная статья про Грецию'), $article->slug);
    }

    // -----------------------------------------------------------------------
    // Create: generated slug collision
    // -----------------------------------------------------------------------

    public function test_admin_generated_slug_collision_returns_validation_error(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);
        $this->createArticle(['title' => 'Same Title', 'slug' => Str::slug('Same Title')]);

        $response = $this->actingAs($admin)->post(route('cabinet.admin.articles.store'), [
            'title' => 'Same Title',
            'content' => 'Другой контент',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_manager_generated_slug_collision_returns_validation_error(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);
        $this->createArticle(['title' => 'Same Title', 'slug' => Str::slug('Same Title')]);

        $response = $this->actingAs($manager)->post(route('cabinet.manager.articles.store'), [
            'title' => 'Same Title',
            'content' => 'Другой контент',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_admin_generated_slug_collision_does_not_create_article(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);
        $this->createArticle(['title' => 'Same Title', 'slug' => Str::slug('Same Title')]);

        $this->actingAs($admin)->post(route('cabinet.admin.articles.store'), [
            'title' => 'Same Title',
            'content' => 'Другой контент',
        ]);

        $this->assertDatabaseCount('articles', 1);
    }

    public function test_manager_generated_slug_collision_does_not_create_article(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);
        $this->createArticle(['title' => 'Same Title', 'slug' => Str::slug('Same Title')]);

        $this->actingAs($manager)->post(route('cabinet.manager.articles.store'), [
            'title' => 'Same Title',
            'content' => 'Другой контент',
        ]);

        $this->assertDatabaseCount('articles', 1);
    }

    // -----------------------------------------------------------------------
    // Update: generated slug collision
    // -----------------------------------------------------------------------

    public function test_admin_update_generated_slug_collision_returns_validation_error_and_preserves_article(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);
        $this->createArticle(['title' => 'Taken Title', 'slug' => Str::slug('Taken Title')]);
        $target = $this->createArticle(['title' => 'Original Title', 'slug' => Str::slug('Original Title'), 'content' => 'Original content']);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.articles.update', $target), [
            'title' => 'Taken Title',
            'content' => 'Changed content',
        ]);

        $response->assertSessionHasErrors('slug');

        $target->refresh();
        $this->assertSame('Original Title', $target->title);
        $this->assertSame(Str::slug('Original Title'), $target->slug);
        $this->assertSame('Original content', $target->content);
    }

    public function test_manager_update_generated_slug_collision_returns_validation_error_and_preserves_article(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);
        $this->createArticle(['title' => 'Taken Title', 'slug' => Str::slug('Taken Title')]);
        $target = $this->createArticle(['title' => 'Original Title', 'slug' => Str::slug('Original Title'), 'content' => 'Original content']);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.articles.update', $target), [
            'title' => 'Taken Title',
            'content' => 'Changed content',
        ]);

        $response->assertSessionHasErrors('slug');

        $target->refresh();
        $this->assertSame('Original Title', $target->title);
        $this->assertSame(Str::slug('Original Title'), $target->slug);
        $this->assertSame('Original content', $target->content);
    }

    // -----------------------------------------------------------------------
    // Update: keeping own generated slug must succeed
    // -----------------------------------------------------------------------

    public function test_admin_update_can_keep_its_own_generated_slug(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);
        $article = $this->createArticle(['title' => 'Stable Title', 'slug' => Str::slug('Stable Title'), 'content' => 'Old content']);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.articles.update', $article), [
            'title' => 'Stable Title',
            'content' => 'New content',
        ]);

        $response->assertRedirect(route('cabinet.admin.articles'));
        $response->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertSame(Str::slug('Stable Title'), $article->slug);
        $this->assertSame('New content', $article->content);
    }

    public function test_manager_update_can_keep_its_own_generated_slug(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);
        $article = $this->createArticle(['title' => 'Stable Title', 'slug' => Str::slug('Stable Title'), 'content' => 'Old content']);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.articles.update', $article), [
            'title' => 'Stable Title',
            'content' => 'New content',
        ]);

        $response->assertRedirect(route('cabinet.manager.articles'));
        $response->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertSame(Str::slug('Stable Title'), $article->slug);
        $this->assertSame('New content', $article->content);
    }

    // -----------------------------------------------------------------------
    // Explicit duplicate slug must still fail
    // -----------------------------------------------------------------------

    public function test_admin_explicit_duplicate_slug_still_returns_validation_error(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);
        $this->createArticle(['title' => 'Existing', 'slug' => 'explicit-slug']);

        $response = $this->actingAs($admin)->post(route('cabinet.admin.articles.store'), [
            'title' => 'Brand New Title',
            'content' => 'Content',
            'slug' => 'explicit-slug',
        ]);

        $response->assertSessionHasErrors('slug');
        $this->assertDatabaseCount('articles', 1);
    }

    public function test_manager_explicit_duplicate_slug_still_returns_validation_error(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);
        $this->createArticle(['title' => 'Existing', 'slug' => 'explicit-slug']);

        $response = $this->actingAs($manager)->post(route('cabinet.manager.articles.store'), [
            'title' => 'Brand New Title',
            'content' => 'Content',
            'slug' => 'explicit-slug',
        ]);

        $response->assertSessionHasErrors('slug');
        $this->assertDatabaseCount('articles', 1);
    }

    // -----------------------------------------------------------------------
    // Blank / whitespace slug is generated before validation
    // -----------------------------------------------------------------------

    public function test_blank_or_whitespace_slug_is_generated_before_validation(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->post(route('cabinet.admin.articles.store'), [
            'title' => 'Whitespace Slug Title',
            'content' => 'Content',
            'slug' => '   ',
        ]);

        $response->assertRedirect(route('cabinet.admin.articles'));
        $response->assertSessionHasNoErrors();

        $article = Article::first();
        $this->assertSame(Str::slug('Whitespace Slug Title'), $article->slug);
    }

    // -----------------------------------------------------------------------
    // Title that generates an empty slug must fail validation, not reach DB
    // -----------------------------------------------------------------------

    public function test_title_that_generates_empty_slug_returns_slug_validation_error(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->post(route('cabinet.admin.articles.store'), [
            'title' => '!!!',
            'content' => 'Content',
        ]);

        $response->assertSessionHasErrors('slug');
        $this->assertDatabaseCount('articles', 0);
    }

    // -----------------------------------------------------------------------
    // E1-FINAL-10: the audit claimed Str::slug() returns '' for a Cyrillic-only
    // title such as "Путешествие по Азии". A read-only runtime probe against
    // this installed Laravel 12.65.0 / PHP 8.3.32 DISPROVED that: ordinary
    // Cyrillic titles transliterate to a non-empty URL-safe slug
    //   "Путешествие по Азии" -> "putesestvie-po-azii"
    //   "Статья"              -> "statia"
    //   "Турция и ОАЭ"        -> "turciia-i-oae"
    // Only punctuation/whitespace-only input yields '' — and that path is
    // already covered above (it fails slug validation, never reaching the DB).
    // These assertions lock the Cyrillic behaviour so the question stays closed.
    // -----------------------------------------------------------------------

    public function test_cyrillic_only_title_generates_a_non_empty_url_safe_slug(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->post(route('cabinet.admin.articles.store'), [
            'title' => 'Путешествие по Азии',
            'content' => 'Содержимое статьи',
        ]);

        $response->assertRedirect(route('cabinet.admin.articles'));
        $response->assertSessionHasNoErrors();

        $article = Article::first();
        $this->assertNotNull($article);
        $this->assertNotSame('', $article->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $article->slug);
        $this->assertSame(Str::slug('Путешествие по Азии'), $article->slug);
    }

    public function test_generated_cyrillic_slug_is_reachable_on_the_public_detail_page(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);

        $this->actingAs($manager)->post(route('cabinet.manager.articles.store'), [
            'title' => 'Турция и ОАЭ',
            'content' => 'Содержимое статьи про направления',
        ])->assertRedirect(route('cabinet.manager.articles'));

        $slug = Article::first()->slug;
        $this->assertNotSame('', $slug);

        $this->get(route('helpful_information.show_interesting_news', ['slug' => $slug]))
            ->assertOk()
            ->assertSee('Турция и ОАЭ', false);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUser(array $roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $name) {
            $role = Role::query()->firstOrCreate(
                ['name' => $name],
                ['description' => Role::availableRoles()[$name] ?? $name]
            );
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function createArticle(array $attributes = []): Article
    {
        return Article::create(array_merge([
            'title' => 'Default Title',
            'slug' => Str::slug('Default Title ' . uniqid()),
            'content' => 'Default content',
        ], $attributes));
    }
}
