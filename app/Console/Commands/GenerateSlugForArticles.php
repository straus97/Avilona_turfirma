<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateSlugForArticles extends Command
{
    protected $signature = 'generate:slug_for_articles';
    protected $description = 'Generate slug for all articles';

    public function handle()
    {
        $articles = Article::all();

        foreach ($articles as $article) {
            $article->slug = Str::slug($article->title, '-');
            $article->save();
        }

        $this->info('Slugs generated successfully!');
    }
}
