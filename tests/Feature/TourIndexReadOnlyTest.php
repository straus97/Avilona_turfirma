<?php

namespace Tests\Feature;

use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TourIndexReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_empty_catalog_shows_empty_state(): void
    {
        $response = $this->get(route('tours.index'));

        $response->assertOk();
        $response->assertViewIs('tours.index');
        $response->assertSee('Туры не найдены');
    }

    public function test_active_tour_displays_complete_dates(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Grand Resort 5★',
            'hotel_name' => 'Grand Resort Slice1',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
        ]);

        $response = $this->get(route('tours.index'));

        $response->assertOk();
        $response->assertSee('Grand Resort Slice1');
        $response->assertSee('10.09.2026');
        $response->assertSee('17.09.2026');
    }

    public function test_inactive_tour_is_excluded(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Hidden Resort 4★',
            'hotel_name' => 'Hidden Resort Inactive',
            'is_active' => false,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
        ]);

        $response = $this->get(route('tours.index'));

        $response->assertOk();
        $response->assertDontSee('Hidden Resort Inactive');
    }

    public function test_soft_deleted_tour_is_excluded(): void
    {
        $tour = Tour::factory()->create([
            'title' => 'Турция, Анталия - Deleted Resort 4★',
            'hotel_name' => 'Deleted Resort Trashed',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
        ]);
        $tour->delete();

        $response = $this->get(route('tours.index'));

        $response->assertOk();
        $response->assertDontSee('Deleted Resort Trashed');
    }

    public function test_departure_city_filter_shows_only_matching_tour(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Moscow Departure 4★',
            'hotel_name' => 'Moscow Departure Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
        ]);

        Tour::factory()->create([
            'title' => 'Турция, Анталия - Kazan Departure 4★',
            'hotel_name' => 'Kazan Departure Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Казань',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
        ]);

        $response = $this->get(route('tours.index', ['departure_city' => 'Москва']));

        $response->assertOk();
        $response->assertSee('Moscow Departure Hotel');
        $response->assertDontSee('Kazan Departure Hotel');
    }

    public function test_destination_country_filter_shows_only_matching_tour(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Turkey Country Hotel 4★',
            'hotel_name' => 'Turkey Country Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
        ]);

        Tour::factory()->create([
            'title' => 'Египет, Хургада - Egypt Country Hotel 4★',
            'hotel_name' => 'Egypt Country Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Египет',
            'destination_city' => 'Хургада',
        ]);

        $response = $this->get(route('tours.index', ['destination_country' => 'Египет']));

        $response->assertOk();
        $response->assertSee('Egypt Country Hotel');
        $response->assertDontSee('Turkey Country Hotel');
    }

    public function test_tour_operator_form_uses_canonical_stored_values(): void
    {
        $response = $this->get(route('tours.index'));

        $response->assertOk();
        $response->assertSee('value="Ambotis"', false);
        $response->assertSee('value="Anex Tour"', false);
        $response->assertSee('value="Biblio Globus"', false);
        $response->assertSee('value="Bon Tour"', false);
        $response->assertSee('value="BSI Group"', false);
        $response->assertSee('value="Coral Travel"', false);
        $response->assertSee('value="Delfin"', false);
        $response->assertSee('value="Express Tours"', false);
        $response->assertSee('value="Good Time"', false);
        $response->assertSee('value="ICS"', false);
        $response->assertSee('value="Intourist"', false);
        $response->assertSee('value="ITM Group"', false);
        $response->assertSee('value="Mouzenidis Travel"', false);
        $response->assertSee('value="PAC Group"', false);
        $response->assertSee('value="Pegas"', false);
        $response->assertSee('value="Russian Express"', false);
        $response->assertSee('value="Sunmar"', false);
        $response->assertSee('value="Tez Tour"', false);
        $response->assertSee('value="TUI"', false);
        $response->assertSee('value="West Travel"', false);
    }

    public function test_tour_operator_filter_shows_only_matching_tour(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Coral Operator Hotel 4★',
            'hotel_name' => 'Coral Operator Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Coral Travel',
        ]);

        Tour::factory()->create([
            'title' => 'Турция, Анталия - TUI Operator Hotel 4★',
            'hotel_name' => 'TUI Operator Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'TUI',
        ]);

        $response = $this->get(route('tours.index', ['tour_operators' => ['Coral Travel']]));

        $response->assertOk();
        $response->assertSee('Coral Operator Hotel');
        $response->assertDontSee('TUI Operator Hotel');
    }

    public function test_multiple_tour_operator_filter_values_are_supported(): void
    {
        Tour::factory()->create([
            'title' => 'Турция, Анталия - Coral Multi Hotel 4★',
            'hotel_name' => 'Coral Multi Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Coral Travel',
        ]);

        Tour::factory()->create([
            'title' => 'Турция, Анталия - TUI Multi Hotel 4★',
            'hotel_name' => 'TUI Multi Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'TUI',
        ]);

        Tour::factory()->create([
            'title' => 'Турция, Анталия - Sunmar Multi Hotel 4★',
            'hotel_name' => 'Sunmar Multi Hotel',
            'is_active' => true,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-17',
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталия',
            'tour_operator' => 'Sunmar',
        ]);

        $response = $this->get(route('tours.index', ['tour_operators' => ['Coral Travel', 'TUI']]));

        $response->assertOk();
        $response->assertSee('Coral Multi Hotel');
        $response->assertSee('TUI Multi Hotel');
        $response->assertDontSee('Sunmar Multi Hotel');
    }

    public function test_index_request_performs_no_database_mutation(): void
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
        ]);

        $before = Tour::withTrashed()->orderBy('id')->get()->toArray();

        $response = $this->get(route('tours.index'));
        $response->assertOk();

        $after = Tour::withTrashed()->orderBy('id')->get()->toArray();

        $this->assertEquals($before, $after);
    }

    public function test_index_request_makes_no_external_http_call(): void
    {
        Http::preventStrayRequests();

        $response = $this->get(route('tours.index'));

        $response->assertOk();
    }
}
