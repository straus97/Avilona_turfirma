<?php

namespace App\Console\Commands;

use App\Models\News;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateSlugForNews extends Command
{
    protected $signature = 'generate:slug_for_news';
    protected $description = 'Generate slug for all news';

    public function handle()
    {
        $news = News::all();

        foreach ($news as $item) {
            $item->slug = Str::slug($item->title, '-');
            $item->save();
        }

        $this->info('Slugs generated successfully!');
    }
}
