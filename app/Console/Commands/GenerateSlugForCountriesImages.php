<?php

namespace App\Console\Commands;

use App\Models\Countries_image;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateSlugForCountriesImages extends Command
{
    protected $signature = 'generate:slug_countries_images';
    protected $description = 'Generate slug for existing countries images';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $countriesImages = Countries_image::all();
        foreach ($countriesImages as $countriesImage) {
            $countriesImage->slug = Str::slug($countriesImage->title);
            $countriesImage->save();
        }

        $this->info('Slug generation completed successfully.');
    }
}
