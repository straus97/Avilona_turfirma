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

    public function test_exact_nights_parameter_filters_tours_and_preserves_public_contract(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Seven Nights Search Hotel 4★',
            'hotel_name' => 'Seven Nights Search Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'nights' => 7,
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Coral Travel',
        ]);

        Tour::factory()->create([
            'title' => 'Турция, Анталия - Ten Nights Search Hotel 4★',
            'hotel_name' => 'Ten Nights Search Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-20',
            'nights' => 10,
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Anex Tour',
        ]);

        $response = $this->getJson('/api/tours/search?' . http_build_query([
            'nights' => 7,
        ]));

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('hotel_name');

        $this->assertTrue($names->contains('Seven Nights Search Hotel'));
        $this->assertFalse($names->contains('Ten Nights Search Hotel'));
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('filters.nights', '7');

        $filters = $response->json('filters');
        $this->assertArrayNotHasKey('nights_min', $filters);
        $this->assertArrayNotHasKey('nights_max', $filters);
        $this->assertArrayNotHasKey('tour_operators', $filters);
    }

    public function test_rating_parameter_filters_by_minimum_hotel_rating_and_preserves_public_contract(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - High Rating Search Hotel 5★',
            'hotel_name' => 'High Rating Search Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Coral Travel',
            'hotel_rating' => 4.8,
        ]);

        Tour::factory()->create([
            'title' => 'Турция, Анталия - Lower Rating Search Hotel 4★',
            'hotel_name' => 'Lower Rating Search Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Anex Tour',
            'hotel_rating' => 4.2,
        ]);

        $response = $this->getJson('/api/tours/search?' . http_build_query([
            'rating' => 4.5,
        ]));

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('hotel_name');

        $this->assertTrue($names->contains('High Rating Search Hotel'));
        $this->assertFalse($names->contains('Lower Rating Search Hotel'));
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('filters.rating', '4.5');

        $filters = $response->json('filters');
        $this->assertArrayNotHasKey('hotel_rating', $filters);
        $this->assertArrayNotHasKey('nights_min', $filters);
        $this->assertArrayNotHasKey('nights_max', $filters);
        $this->assertArrayNotHasKey('tour_operators', $filters);
    }

    public function test_charter_parameter_filters_true_and_false_values_and_preserves_public_contract(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Charter Search Hotel 4★',
            'hotel_name' => 'Charter Search Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Coral Travel',
            'is_charter' => true,
        ]);

        Tour::factory()->create([
            'title' => 'Турция, Анталия - Scheduled Search Hotel 4★',
            'hotel_name' => 'Scheduled Search Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Anex Tour',
            'is_charter' => false,
        ]);

        $charterResponse = $this->getJson('/api/tours/search?' . http_build_query([
            'charter' => 1,
        ]));

        $charterResponse->assertOk();
        $charterNames = collect($charterResponse->json('data'))->pluck('hotel_name');

        $this->assertTrue($charterNames->contains('Charter Search Hotel'));
        $this->assertFalse($charterNames->contains('Scheduled Search Hotel'));
        $charterResponse->assertJsonPath('meta.total', 1);
        $charterResponse->assertJsonPath('filters.charter', '1');

        $charterFilters = $charterResponse->json('filters');
        $this->assertArrayNotHasKey('is_charter', $charterFilters);
        $this->assertArrayNotHasKey('hotel_rating', $charterFilters);
        $this->assertArrayNotHasKey('nights_min', $charterFilters);
        $this->assertArrayNotHasKey('nights_max', $charterFilters);
        $this->assertArrayNotHasKey('tour_operators', $charterFilters);

        $scheduledResponse = $this->getJson('/api/tours/search?' . http_build_query([
            'charter' => 0,
        ]));

        $scheduledResponse->assertOk();
        $scheduledNames = collect($scheduledResponse->json('data'))->pluck('hotel_name');

        $this->assertTrue($scheduledNames->contains('Scheduled Search Hotel'));
        $this->assertFalse($scheduledNames->contains('Charter Search Hotel'));
        $scheduledResponse->assertJsonPath('meta.total', 1);
        $scheduledResponse->assertJsonPath('filters.charter', '0');

        $scheduledFilters = $scheduledResponse->json('filters');
        $this->assertArrayNotHasKey('is_charter', $scheduledFilters);
        $this->assertArrayNotHasKey('hotel_rating', $scheduledFilters);
        $this->assertArrayNotHasKey('nights_min', $scheduledFilters);
        $this->assertArrayNotHasKey('nights_max', $scheduledFilters);
        $this->assertArrayNotHasKey('tour_operators', $scheduledFilters);
    }

    public function test_direct_flight_parameter_filters_true_and_false_values_and_preserves_public_contract(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Direct Flight Search Hotel 4★',
            'hotel_name' => 'Direct Flight Search Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Coral Travel',
            'is_direct' => true,
        ]);

        Tour::factory()->create([
            'title' => 'Турция, Анталия - Connecting Flight Search Hotel 4★',
            'hotel_name' => 'Connecting Flight Search Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Anex Tour',
            'is_direct' => false,
        ]);

        $directResponse = $this->getJson('/api/tours/search?' . http_build_query([
            'direct_flight' => 1,
        ]));

        $directResponse->assertOk();
        $directNames = collect($directResponse->json('data'))->pluck('hotel_name');

        $this->assertTrue($directNames->contains('Direct Flight Search Hotel'));
        $this->assertFalse($directNames->contains('Connecting Flight Search Hotel'));
        $directResponse->assertJsonPath('meta.total', 1);
        $directResponse->assertJsonPath('filters.direct_flight', '1');

        $directFilters = $directResponse->json('filters');
        $this->assertArrayNotHasKey('is_direct', $directFilters);
        $this->assertArrayNotHasKey('is_charter', $directFilters);
        $this->assertArrayNotHasKey('hotel_rating', $directFilters);
        $this->assertArrayNotHasKey('nights_min', $directFilters);
        $this->assertArrayNotHasKey('nights_max', $directFilters);
        $this->assertArrayNotHasKey('tour_operators', $directFilters);

        $connectingResponse = $this->getJson('/api/tours/search?' . http_build_query([
            'direct_flight' => 0,
        ]));

        $connectingResponse->assertOk();
        $connectingNames = collect($connectingResponse->json('data'))->pluck('hotel_name');

        $this->assertTrue($connectingNames->contains('Connecting Flight Search Hotel'));
        $this->assertFalse($connectingNames->contains('Direct Flight Search Hotel'));
        $connectingResponse->assertJsonPath('meta.total', 1);
        $connectingResponse->assertJsonPath('filters.direct_flight', '0');

        $connectingFilters = $connectingResponse->json('filters');
        $this->assertArrayNotHasKey('is_direct', $connectingFilters);
        $this->assertArrayNotHasKey('is_charter', $connectingFilters);
        $this->assertArrayNotHasKey('hotel_rating', $connectingFilters);
        $this->assertArrayNotHasKey('nights_min', $connectingFilters);
        $this->assertArrayNotHasKey('nights_max', $connectingFilters);
        $this->assertArrayNotHasKey('tour_operators', $connectingFilters);
    }

    public function test_unsupported_instant_confirmation_parameter_is_not_exposed_or_applied(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Instant Parameter Control Hotel A',
            'hotel_name' => 'Instant Parameter Control Hotel A',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Coral Travel',
        ]);

        Tour::factory()->create([
            'title' => 'Турция, Анталия - Instant Parameter Control Hotel B',
            'hotel_name' => 'Instant Parameter Control Hotel B',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Anex Tour',
        ]);

        $enabledResponse = $this->getJson('/api/tours/search?' . http_build_query([
            'instant_confirmation' => 1,
        ]));

        $enabledResponse->assertOk();
        $enabledNames = collect($enabledResponse->json('data'))->pluck('hotel_name');

        $this->assertTrue($enabledNames->contains('Instant Parameter Control Hotel A'));
        $this->assertTrue($enabledNames->contains('Instant Parameter Control Hotel B'));
        $enabledResponse->assertJsonPath('meta.total', 2);

        $enabledFilters = $enabledResponse->json('filters');
        $this->assertArrayNotHasKey('instant_confirmation', $enabledFilters);
        $this->assertArrayNotHasKey('is_direct', $enabledFilters);
        $this->assertArrayNotHasKey('is_charter', $enabledFilters);
        $this->assertArrayNotHasKey('hotel_rating', $enabledFilters);
        $this->assertArrayNotHasKey('nights_min', $enabledFilters);
        $this->assertArrayNotHasKey('nights_max', $enabledFilters);
        $this->assertArrayNotHasKey('tour_operators', $enabledFilters);

        $disabledResponse = $this->getJson('/api/tours/search?' . http_build_query([
            'instant_confirmation' => 0,
        ]));

        $disabledResponse->assertOk();
        $disabledNames = collect($disabledResponse->json('data'))->pluck('hotel_name');

        $this->assertTrue($disabledNames->contains('Instant Parameter Control Hotel A'));
        $this->assertTrue($disabledNames->contains('Instant Parameter Control Hotel B'));
        $disabledResponse->assertJsonPath('meta.total', 2);

        $disabledFilters = $disabledResponse->json('filters');
        $this->assertArrayNotHasKey('instant_confirmation', $disabledFilters);
        $this->assertArrayNotHasKey('is_direct', $disabledFilters);
        $this->assertArrayNotHasKey('is_charter', $disabledFilters);
        $this->assertArrayNotHasKey('hotel_rating', $disabledFilters);
        $this->assertArrayNotHasKey('nights_min', $disabledFilters);
        $this->assertArrayNotHasKey('nights_max', $disabledFilters);
        $this->assertArrayNotHasKey('tour_operators', $disabledFilters);
    }

    public function test_rating_sort_orders_by_hotel_rating_not_hotel_stars(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Higher Rating Lower Stars Hotel',
            'hotel_name' => 'Higher Rating Lower Stars Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Coral Travel',
            'hotel_rating' => 4.9,
            'hotel_stars' => 3,
        ]);

        Tour::factory()->create([
            'title' => 'Турция, Анталия - Lower Rating Higher Stars Hotel',
            'hotel_name' => 'Lower Rating Higher Stars Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Anex Tour',
            'hotel_rating' => 4.1,
            'hotel_stars' => 5,
        ]);

        $response = $this->getJson('/api/tours/search?' . http_build_query([
            'sort_by' => 'rating',
        ]));

        $response->assertOk();
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('data.0.hotel_name', 'Higher Rating Lower Stars Hotel');
        $response->assertJsonPath('data.1.hotel_name', 'Lower Rating Higher Stars Hotel');
        $response->assertJsonPath('filters.sort_by', 'rating');

        $filters = $response->json('filters');
        $this->assertArrayNotHasKey('hotel_rating', $filters);
        $this->assertArrayNotHasKey('is_direct', $filters);
        $this->assertArrayNotHasKey('is_charter', $filters);
        $this->assertArrayNotHasKey('nights_min', $filters);
        $this->assertArrayNotHasKey('nights_max', $filters);
        $this->assertArrayNotHasKey('tour_operators', $filters);
    }

    public function test_hotel_name_parameter_performs_partial_match_and_preserves_public_contract(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Sunrise Grand Resort Search',
            'hotel_name' => 'Sunrise Grand Resort',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Coral Travel',
        ]);

        Tour::factory()->create([
            'title' => 'Турция, Анталия - Sunset Grand Palace Search',
            'hotel_name' => 'Sunset Grand Palace',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Anex Tour',
        ]);

        Tour::factory()->create([
            'title' => 'Турция, Анталия - Azure Coast Hotel Search',
            'hotel_name' => 'Azure Coast Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Sunmar',
        ]);

        $response = $this->getJson('/api/tours/search?' . http_build_query([
            'hotel_name' => 'Grand',
        ]));

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('hotel_name');

        $this->assertTrue($names->contains('Sunrise Grand Resort'));
        $this->assertTrue($names->contains('Sunset Grand Palace'));
        $this->assertFalse($names->contains('Azure Coast Hotel'));
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('filters.hotel_name', 'Grand');

        $filters = $response->json('filters');
        $this->assertArrayNotHasKey('hotel_rating', $filters);
        $this->assertArrayNotHasKey('is_direct', $filters);
        $this->assertArrayNotHasKey('is_charter', $filters);
        $this->assertArrayNotHasKey('nights_min', $filters);
        $this->assertArrayNotHasKey('nights_max', $filters);
        $this->assertArrayNotHasKey('tour_operators', $filters);
    }
}
