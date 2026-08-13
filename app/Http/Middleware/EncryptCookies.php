<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Стage 13: cookie-баннер согласия пишет это cookie напрямую из JS
        // (document.cookie), поэтому Laravel не должен пытаться его
        // расшифровать как собственное зашифрованное cookie — иначе
        // серверная проверка согласия всегда будет видеть null после
        // перезагрузки страницы. Это единственное исключение; шифрование
        // остальных cookie остаётся включённым.
        'avilona_cookie_consent',
    ];
}
