<?php

namespace Database\Factories;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

class TourFactory extends Factory
{
    protected $model = Tour::class;

    public function definition(): array
    {
        $departureCities = ['Москва', 'Санкт-Петербург', 'Екатеринбург', 'Новосибирск', 'Казань'];
        
        $destinations = [
            'Турция' => ['Анталия', 'Стамбул', 'Бодрум', 'Мармарис', 'Аланья', 'Кемер', 'Сиде'],
            'Египет' => ['Хургада', 'Шарм-эль-Шейх', 'Марса-Алам', 'Таба'],
            'ОАЭ' => ['Дубай', 'Абу-Даби', 'Шарджа', 'Рас-эль-Хайма'],
            'Тайланд' => ['Пхукет', 'Паттайя', 'Самуи', 'Краби', 'Бангкок'],
            'Испания' => ['Барселона', 'Мадрид', 'Коста-Брава', 'Майорка', 'Тенерифе'],
        ];
        
        $mealTypes = ['BB', 'HB', 'FB', 'AI', 'UAI'];
        $beachLines = ['1-я линия', '< 100м', '2-я линия', '< 500м', '3-я линия'];
        
        $country = $this->faker->randomElement(array_keys($destinations));
        $city = $this->faker->randomElement($destinations[$country]);
        
        $startDate = $this->faker->dateTimeBetween('+1 week', '+6 months');
        $nights = $this->faker->randomElement([7, 10, 11, 14, 21]);
        $endDate = (clone $startDate)->modify("+{$nights} days");
        
        $hotelStars = $this->faker->numberBetween(3, 5);
        $mealType = $this->faker->randomElement($mealTypes);
        
        // Генерация названия отеля
        $hotelPrefixes = ['Grand', 'Royal', 'Beach', 'Golden', 'Paradise', 'Sunset', 'Crystal', 'Diamond'];
        $hotelSuffixes = ['Resort', 'Hotel', 'Spa', 'Palace', 'Club', 'Beach'];
        $hotelName = $this->faker->randomElement($hotelPrefixes) . ' ' . 
                     $this->faker->randomElement($hotelSuffixes);
        
        // Расчет цены в зависимости от звездности и типа питания
        $basePrice = match($hotelStars) {
            3 => $this->faker->numberBetween(30000, 60000),
            4 => $this->faker->numberBetween(50000, 100000),
            5 => $this->faker->numberBetween(80000, 200000),
        };
        
        $mealPriceMultiplier = match($mealType) {
            'BB' => 1.0,
            'HB' => 1.1,
            'FB' => 1.2,
            'AI' => 1.4,
            'UAI' => 1.6,
        };
        
        $price = round($basePrice * $mealPriceMultiplier, -2);
        $priceChild = round($price * 0.7, -2);
        
        $rating = $this->faker->randomFloat(1, 7.0, 9.9);
        
        $facilities = $this->faker->randomElements([
            'Бассейн',
            'Wi-Fi',
            'Спа-центр',
            'Фитнес-центр',
            'Ресторан',
            'Бар',
            'Детская площадка',
            'Анимация',
            'Пляжные полотенца',
            'Трансфер',
            'Парковка',
            'Кондиционер',
        ], $this->faker->numberBetween(5, 10));

        return [
            'title' => "{$country}, {$city} - {$hotelName} {$hotelStars}★",
            'slug' => null, // Автоматически сгенерируется в модели
            'description' => "Отдых в отеле {$hotelName} {$hotelStars}★ в {$city}, {$country}. " . 
                           $this->faker->paragraph(2),
            'price' => $price,
            'price_child' => $priceChild,
            'departure_city' => $this->faker->randomElement($departureCities),
            'destination_country' => $country,
            'destination_city' => $city,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'nights' => $nights,
            'hotel_name' => $hotelName,
            'hotel_stars' => $hotelStars,
            'meal_type' => $mealType,
            'max_tourists' => $this->faker->numberBetween(10, 50),
            'available_seats' => null, // Автоматически заполнится в модели
            'facilities' => $facilities,
            'image_url' => '/img/tours/tour-' . $this->faker->numberBetween(1, 10) . '.jpg',
            'gallery' => [
                '/img/tours/gallery-1.jpg',
                '/img/tours/gallery-2.jpg',
                '/img/tours/gallery-3.jpg',
            ],
            'is_active' => $this->faker->boolean(95), // 95% активных
            'is_hot_deal' => $this->faker->boolean(20), // 20% горящих
        ];
    }

    /**
     * Горящее предложение
     */
    public function hotDeal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hot_deal' => true,
            'price' => $attributes['price'] * 0.7, // Скидка 30%
        ]);
    }

    /**
     * Премиум тур (5 звезд, UAI)
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'hotel_stars' => 5,
            'meal_type' => 'UAI',
            'price' => $this->faker->numberBetween(150000, 300000),
        ]);
    }

    /**
     * Бюджетный тур (3 звезды, BB)
     */
    public function budget(): static
    {
        return $this->state(fn (array $attributes) => [
            'hotel_stars' => 3,
            'meal_type' => 'BB',
            'price' => $this->faker->numberBetween(30000, 50000),
        ]);
    }
}
