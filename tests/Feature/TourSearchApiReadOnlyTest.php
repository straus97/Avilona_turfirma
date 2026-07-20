<?php

namespace Tests\Feature;

use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TourSearchApiReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_search_returns_read_only_paginated_structure(): void
    {
        $response = $this->getJson('/api/tours/search');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('meta.total', 0);
        $response->assertJsonPath('filters', []);
        $this->assertArrayHasKey('meta', $response->json());
    }

    public function test_search_returns_active_tours_and_excludes_inactive_and_soft_deleted_tours(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Active Search Hotel 4★',
            'hotel_name' => 'Active Search Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Coral Travel',
        ]);

        Tour::factory()->create([
            'title' => 'Турция, Анталия - Inactive Search Hotel 4★',
            'hotel_name' => 'Inactive Search Hotel',
            'is_active' => false,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'TUI',
        ]);

        $trashedTour = Tour::factory()->create([
            'title' => 'Турция, Анталия - Trashed Search Hotel 4★',
            'hotel_name' => 'Trashed Search Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Sunmar',
        ]);
        $trashedTour->delete();

        $response = $this->getJson('/api/tours/search');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('hotel_name');

        $this->assertTrue($names->contains('Active Search Hotel'));
        $this->assertFalse($names->contains('Inactive Search Hotel'));
        $this->assertFalse($names->contains('Trashed Search Hotel'));
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_singular_tour_operator_parameter_filters_by_canonical_operator_name(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Coral Search Hotel 4★',
            'hotel_name' => 'Coral Search Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Coral Travel',
        ]);

        Tour::factory()->create([
            'title' => 'Турция, Анталия - Anex Search Hotel 4★',
            'hotel_name' => 'Anex Search Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Anex Tour',
        ]);

        $response = $this->getJson('/api/tours/search?' . http_build_query([
            'tour_operator' => 'Coral Travel',
        ]));

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('hotel_name');

        $this->assertTrue($names->contains('Coral Search Hotel'));
        $this->assertFalse($names->contains('Anex Search Hotel'));
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('filters.tour_operator', 'Coral Travel');
        $this->assertArrayNotHasKey('tour_operators', $response->json('filters'));
    }

    public function test_search_request_performs_no_database_mutation(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Readonly Snapshot Hotel 4★',
            'hotel_name' => 'Readonly Snapshot Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Coral Travel',
        ]);

        $before = Tour::withTrashed()->orderBy('id')->get()->toArray();

        $response = $this->getJson('/api/tours/search');
        $response->assertOk();

        $after = Tour::withTrashed()->orderBy('id')->get()->toArray();

        $this->assertEquals($before, $after);
    }

    public function test_search_request_makes_no_external_http_call(): void
    {
        Http::preventStrayRequests();

        $response = $this->getJson('/api/tours/search');

        $response->assertOk();
    }
}
