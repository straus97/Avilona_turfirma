<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicRobotsConsistencyTest extends TestCase
{
    /**
     * @return array<int, array{directive: string, value: string}>
     */
    private function loadDirectives(): array
    {
        $path = base_path('robots.txt');

        $this->assertFileExists($path);

        $contents = file_get_contents($path);

        $this->assertNotEmpty($contents, 'robots.txt must not be empty');

        $directives = [];

        foreach (preg_split('/\R/', $contents) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, ':')) {
                continue;
            }

            [$directive, $value] = explode(':', $line, 2);

            $directives[] = [
                'directive' => trim($directive),
                'value' => trim($value),
            ];
        }

        return $directives;
    }

    public function test_robots_contains_user_agent_wildcard_section(): void
    {
        $directives = $this->loadDirectives();

        $userAgents = array_column(
            array_filter($directives, fn ($d) => strcasecmp($d['directive'], 'User-agent') === 0),
            'value'
        );

        $this->assertContains('*', $userAgents, 'robots.txt must contain a User-agent: * section');
    }

    public function test_exactly_one_canonical_sitemap_directive(): void
    {
        $directives = $this->loadDirectives();

        $sitemaps = array_column(
            array_filter($directives, fn ($d) => strcasecmp($d['directive'], 'Sitemap') === 0),
            'value'
        );

        $this->assertCount(1, $sitemaps, 'robots.txt must contain exactly one Sitemap directive');
        $this->assertSame('https://avilona.ru/sitemap.xml', $sitemaps[0]);
    }

    public function test_no_sitemap_directive_uses_www_host(): void
    {
        $directives = $this->loadDirectives();

        foreach ($directives as $d) {
            if (strcasecmp($d['directive'], 'Sitemap') === 0) {
                $this->assertStringNotContainsString('www.avilona.ru', $d['value']);
            }
        }
    }

    public function test_zero_allow_directives_remain(): void
    {
        $directives = $this->loadDirectives();

        $allows = array_filter($directives, fn ($d) => strcasecmp($d['directive'], 'Allow') === 0);

        $this->assertCount(0, $allows, 'robots.txt must not contain any Allow directives');
    }

    public function test_current_private_route_prefixes_are_disallowed(): void
    {
        $directives = $this->loadDirectives();

        $disallows = array_column(
            array_filter($directives, fn ($d) => strcasecmp($d['directive'], 'Disallow') === 0),
            'value'
        );

        $privatePrefixes = [
            '/login',
            '/register',
            '/forgot-password',
            '/reset-password',
            '/verify-email',
            '/confirm-password',
            '/admin',
            '/manager',
            '/cabinet',
            '/profile',
        ];

        foreach ($privatePrefixes as $prefix) {
            $this->assertTrue(
                in_array($prefix, $disallows, true),
                "robots.txt must disallow private route prefix {$prefix}"
            );
        }
    }

    public function test_representative_technical_disallows_preserved(): void
    {
        $directives = $this->loadDirectives();

        $disallows = array_column(
            array_filter($directives, fn ($d) => strcasecmp($d['directive'], 'Disallow') === 0),
            'value'
        );

        $technical = [
            '/vendor/',
            '/storage/',
            '/resources/',
            '/.env',
        ];

        foreach ($technical as $prefix) {
            $this->assertTrue(
                in_array($prefix, $disallows, true),
                "robots.txt must preserve technical disallow rule {$prefix}"
            );
        }
    }

    public function test_stale_public_allow_entries_do_not_reappear(): void
    {
        $directives = $this->loadDirectives();

        $stalePublicPaths = [
            '/public/assets/',
            '/public/css/',
            '/public/js/',
            '/public/images/',
            '/countries/',
            '/destinations/',
            '/helpful_information/',
            '/home/',
            '/contact/',
            '/reviews/',
            '/about/',
            '/news/',
            '/special/',
        ];

        foreach ($directives as $d) {
            if (strcasecmp($d['directive'], 'Allow') === 0) {
                $this->assertNotContains($d['value'], $stalePublicPaths);
            }
        }

        $allowCount = count(array_filter($directives, fn ($d) => strcasecmp($d['directive'], 'Allow') === 0));
        $this->assertSame(0, $allowCount);
    }
}
