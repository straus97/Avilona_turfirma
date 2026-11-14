<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * NOV-PUBLIC-03B-L3: contract of scripts/release/build-public-package.php and its manifest.
 * Pure PHPUnit (no application boot, no database). The build test packages the committed HEAD
 * into a temporary directory outside the repository.
 */
class PublicReleasePackagingTest extends TestCase
{
    private string $root;
    private array $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 2);
        $this->manifest = json_decode(
            file_get_contents($this->root . '/scripts/release/public-package-manifest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function allowed(string $path): bool
    {
        foreach ($this->manifest['allow_regex'] as $re) {
            if (preg_match("~$re~", $path)) {
                return true;
            }
        }
        return false;
    }

    private function denied(string $path): bool
    {
        foreach ($this->manifest['forbidden_path_regex'] as $re) {
            if (preg_match("~$re~i", $path)) {
                return true;
            }
        }
        return false;
    }

    public function test_required_files_are_allowed_and_not_denied_and_exist(): void
    {
        foreach ($this->manifest['required_files'] as $file) {
            $this->assertTrue($this->allowed($file), "$file must be allowlisted");
            $this->assertFalse($this->denied($file), "$file must not be denylisted");
            $this->assertFileExists($this->root . '/' . $file);
        }
    }

    public function test_sensitive_and_development_paths_are_rejected(): void
    {
        $bad = [
            '.env', 'config/.env', '.env.production', 'database/dump.sql', 'storage/app/documents/client.pdf',
            'storage/logs/laravel.log', 'public/documents/x.docx', 'public/documents/x.doc', 'public/img/a.psd',
            'tests/Feature/X.php', 'docs/roadmap.md', 'node_modules/x/index.js', 'vendor/autoload.php',
            '.claude/settings.json', 'phpunit.xml', 'package.json', 'turfirma_dev_schema_only.sql',
            'backup-db.bat', 'database/seeders/UserSeeder.php', 'public/plugins/jquery/jquery.js',
            'public/dist/js/adminlte.js', 'app/backup.zip',
        ];
        foreach ($bad as $path) {
            $this->assertTrue(!$this->allowed($path) || $this->denied($path), "$path must be rejected");
        }
    }

    public function test_dev_only_trees_are_not_requested_from_git_archive(): void
    {
        $specs = $this->manifest['archive_pathspecs'];
        foreach (['tests', 'docs', 'node_modules', 'vendor', '.claude', 'scripts', 'database/seeders'] as $p) {
            $this->assertNotContains($p, $specs);
        }
        $this->assertContains(':(exclude)public/plugins', $specs);
        $this->assertContains(':(exclude)public/dist', $specs);
    }

    public function test_application_source_never_hardcodes_the_public_only_switch(): void
    {
        foreach (['config/release.php', 'bootstrap/app.php', 'app/Providers/RouteServiceProvider.php'] as $file) {
            $src = file_get_contents($this->root . '/' . $file);
            $this->assertDoesNotMatchRegularExpression('~RELEASE_PUBLIC_ONLY[\'"]?\s*,\s*true|public_only[\'"]\s*=>\s*true~', $src);
        }
    }

    public function test_build_is_validated_and_byte_reproducible(): void
    {
        if (!is_dir($this->root . '/.git') || trim((string) shell_exec('git --version 2>&1')) === '') {
            $this->markTestSkipped('git checkout required');
        }
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'avilona-pkg-test-' . bin2hex(random_bytes(4));
        $hashes = [];
        try {
            foreach (['a', 'b'] as $run) {
                $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->root . '/scripts/release/build-public-package.php')
                    . ' --out=' . escapeshellarg("$tmp/$run") . ' 2>&1';
                exec($cmd, $out, $code);
                $this->assertSame(0, $code, implode("\n", $out));
                $files = glob("$tmp/$run/*.tar.gz");
                $this->assertCount(1, $files);
                $hashes[] = hash_file('sha256', $files[0]);
                $listing = file_get_contents(glob("$tmp/$run/*.files.txt")[0]);
                $this->assertStringNotContainsString('.env', $listing);
                $this->assertDoesNotMatchRegularExpression('~\.(docx?|psd|sql)$~mi', $listing);
                $this->assertStringContainsString("public/documents/User_Agreement.pdf\n", $listing);
                $this->assertStringNotContainsString("public/plugins/", $listing);
                $out = [];
            }
            $this->assertSame($hashes[0], $hashes[1], 'two builds of the same commit must be byte-identical');
        } finally {
            foreach (glob("$tmp/*/*") ?: [] as $f) {
                @unlink($f);
            }
            foreach (glob("$tmp/*") ?: [] as $d) {
                @rmdir($d);
            }
            @rmdir($tmp);
        }
    }
}
