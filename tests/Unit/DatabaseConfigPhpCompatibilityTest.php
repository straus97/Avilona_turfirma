<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * config/database.php обязан загружаться без PHP-deprecation на всех
 * поддерживаемых версиях (prod: 8.3, локально: 8.4/8.5). Константы
 * PDO::MYSQL_ATTR_* объявлены deprecated в PHP 8.5 в пользу Pdo\Mysql::ATTR_*.
 */
class DatabaseConfigPhpCompatibilityTest extends TestCase
{
    public function test_database_config_loads_without_php_deprecations(): void
    {
        $deprecations = [];

        set_error_handler(function (int $errno, string $errstr) use (&$deprecations) {
            $deprecations[] = $errstr;

            return true;
        }, E_DEPRECATED | E_USER_DEPRECATED);

        try {
            require config_path('database.php');
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
    }

    public function test_mysql_ssl_ca_option_maps_to_pdo_attribute_when_configured(): void
    {
        $this->setEnvForTest('MYSQL_ATTR_SSL_CA', '/synthetic/ca.pem');

        try {
            $config = require config_path('database.php');
        } finally {
            $this->setEnvForTest('MYSQL_ATTR_SSL_CA', null);
        }

        $sslCaAttribute = PHP_VERSION_ID >= 80500
            ? \Pdo\Mysql::ATTR_SSL_CA
            : \PDO::MYSQL_ATTR_SSL_CA;

        $this->assertSame(
            [$sslCaAttribute => '/synthetic/ca.pem'],
            $config['connections']['mysql']['options']
        );
    }

    public function test_mysql_options_are_empty_when_ssl_ca_not_configured(): void
    {
        $this->setEnvForTest('MYSQL_ATTR_SSL_CA', null);

        $config = require config_path('database.php');

        $this->assertSame([], $config['connections']['mysql']['options']);
    }

    private function setEnvForTest(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            return;
        }

        $_ENV[$key] = $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }
}
