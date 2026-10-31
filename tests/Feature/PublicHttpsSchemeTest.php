<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * NOV-PUBLIC-01: при HTTPS-адресе приложения (APP_URL=https://...) ссылки и
 * ресурсы строятся по https, даже если PHP видит запрос как http (TLS
 * терминируется выше по цепочке). При http-адресе поведение не меняется.
 */
class PublicHttpsSchemeTest extends TestCase
{
    protected function tearDown(): void
    {
        URL::forceScheme('http');
        URL::forceRootUrl(null);

        parent::tearDown();
    }

    public function test_https_app_url_forces_https_links_and_assets(): void
    {
        config(['app.url' => 'https://avilona.example']);
        URL::forceScheme('http'); // исходное состояние: запрос виден как http

        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', route('contact.index'));
        $this->assertStringStartsWith('https://', asset('css/unified.css'));
    }

    public function test_http_app_url_leaves_the_request_scheme_untouched(): void
    {
        config(['app.url' => 'http://localhost']);
        URL::forceScheme('http');

        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('http://', route('contact.index'));
    }
}
