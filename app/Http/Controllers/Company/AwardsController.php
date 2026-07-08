<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Award;
use Illuminate\Support\Facades\Cache;

class AwardsController extends Controller
{
    public function __invoke()
    {
        $awards = Cache::remember('awards_all', 3600, function () {
            return Award::select('id', 'image', 'category')
                ->orderBy('id')
                ->get();
        });

        return view('company.awards', compact('awards'));
    }
}