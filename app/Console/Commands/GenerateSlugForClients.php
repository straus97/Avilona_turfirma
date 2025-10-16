<?php

namespace App\Console\Commands;

use App\Models\OurClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateSlugForClients extends Command
{
    protected $signature = 'generate:slug_for_clients';
    protected $description = 'Generate slug for all clients';

    public function handle()
    {
        $clients = OurClient::all();

        foreach ($clients as $client) {
            $client->slug = Str::slug($client->title, '-');
            $client->save();
        }

        $this->info('Slugs generated successfully!');
    }
}
