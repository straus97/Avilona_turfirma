<?php

namespace App\Http\Controllers\Captcha;

use App\Http\Controllers\Controller;
use Mews\Captcha\Facades\Captcha;

class ReloadCaptchaController extends Controller
{
    public function __invoke()
    {
        return response()->json(['captcha' => Captcha::src('flat') . '?' . time()]);
    }
}
