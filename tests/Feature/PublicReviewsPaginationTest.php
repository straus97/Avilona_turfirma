<?php

namespace Tests\Feature;

use App\Models\Reviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2-A6-I2 (E): approved follow-up carried over from E2-A6-I1 — the public
 * Reviews list moves from paginate(4) to paginate(6), so the 2-column
 * desktop grid renders three balanced rows instead of two uneven ones.
 *
 * Only the per-page count changes here; ordering, the is_published filter
 * and the withdrawn-consent exclusion are Stage 13 contracts already locked
 * by ReviewWithdrawalPublicationEnforcementTest and friends, and are not
 * re-tested in this file.
 */
class PublicReviewsPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function createPublishedReview(string $marker): void
    {
        Reviews::create([
            'name' => $marker,
            'title' => null,
            'content' => $marker . '-CONTENT ' . str_repeat('a', 60),
            'image' => null,
            'is_published' => true,
        ]);
    }

    public function test_first_page_shows_six_reviews(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createPublishedReview('Reviewer-' . $i);
        }

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $this->assertSame(6, substr_count($response->getContent(), 'class="e2-review-item'));
    }

    public function test_second_page_shows_the_remaining_review(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createPublishedReview('Reviewer-' . $i);
        }

        $response = $this->get(route('review.index', ['page' => 2]));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'class="e2-review-item'));
    }

    public function test_exactly_six_reviews_do_not_paginate_to_a_second_page(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->createPublishedReview('Reviewer-' . $i);
        }

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $this->assertSame(6, substr_count($response->getContent(), 'class="e2-review-item'));
        $response->assertDontSee('page=2', false);
    }
}
