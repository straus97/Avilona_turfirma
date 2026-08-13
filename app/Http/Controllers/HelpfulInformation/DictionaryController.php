<?php

namespace App\Http\Controllers\HelpfulInformation;

use App\Http\Controllers\Controller;

class DictionaryController extends Controller
{
    public function __invoke()
    {
        return view('helpful_information.travel_dictionary');
    }
}
