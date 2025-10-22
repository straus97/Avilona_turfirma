<?php

namespace App\Console\Commands;

use App\Models\Tour;
use Illuminate\Console\Command;

class UpdateToursCommand extends Command
{
    protected $signature = 'tours:update';
    protected $description = 'Update existing tours with new fields';

    public function handle()
    {
        $tours = Tour::all();
        $tourOperators = [
            'Ambotis', 'Anex Tour', 'Biblio Globus', 'Bon Tour', 'BSI Group',
            'Coral Travel', 'Delfin', 'Express Tours', 'Good Time', 'ICS',
            'Intourist', 'ITM Group', 'Mouzenidis Travel', 'PAC Group',
            'Pegas', 'Russian Express', 'Sunmar', 'Tez Tour', 'TUI', 'West Travel'
        ];
        $beachLines = ['1-я линия', '< 100м', '2-я линия', '< 500м', '3-я линия'];

        foreach ($tours as $tour) {
            $tour->update([
                'tour_operator' => $tourOperators[array_rand($tourOperators)],
                'beach_line' => $beachLines[array_rand($beachLines)],
                'hotel_rating' => rand(70, 99) / 10,
                'is_charter' => rand(0, 1),
                'is_direct' => rand(0, 1),
                'resort' => $tour->destination_city,
                'included_services' => 'Перелет, трансфер, проживание, питание по программе, страховка',
                'not_included_services' => 'Виза, экскурсии, личные расходы, чаевые'
            ]);
        }

        $this->info("Updated {$tours->count()} tours");
        return 0;
    }
}
