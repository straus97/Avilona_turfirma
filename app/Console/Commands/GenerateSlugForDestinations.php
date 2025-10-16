<?php

namespace App\Console\Commands;

use App\Models\Destination_image;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateSlugForDestinations extends Command
{
    protected $signature = 'generate:slug_for_destinations';
    protected $description = 'Generate slug for all destination images';

    public function handle()
    {
        $destinations = Destination_image::all();

        foreach ($destinations as $destination) {
            $destination->slug = Str::slug($destination->title, '-');
            $destination->save();
        }

        $this->info('Slugs generated successfully!');
    }
}
