<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DeploymentConfigTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(__DIR__.'/../../'.$path);
    }

    public function test_node_runtime_and_lockfile_agree_with_wranglers_runtime_requirement(): void
    {
        $package = json_decode($this->source('package.json'), true);
        $lock = json_decode($this->source('package-lock.json'), true);
        $this->assertSame('22', trim($this->source('.nvmrc')));
        $this->assertSame('>=22 <23', $package['engines']['node']);
        $this->assertSame($package['engines']['node'], $lock['packages']['']['engines']['node']);
        $this->assertSame('>=22.0.0', $lock['packages']['node_modules/wrangler']['engines']['node']);
    }

    public function test_oauth_configuration_has_no_checked_in_client_credentials(): void
    {
        // Compare expressions, not credential values, so failures never reveal secrets.
        $source = $this->source('config/google.php');
        $this->assertTrue(strpos($source, "'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),") !== false);
        $this->assertTrue(strpos($source, "'client_id' => env('GOOGLE_CLIENT_ID', ''),") !== false);
    }

    public function test_private_database_ca_is_owned_by_the_php_worker(): void
    {
        $source = $this->source('cloudflare/start-container.sh');
        $this->assertStringContainsString('chmod 600 /tmp/mysql-ca.pem', $source);
        $this->assertStringContainsString('chown www-data:www-data /tmp/mysql-ca.pem', $source);
        $this->assertLessThan(strpos($source, 'exec apache2-foreground'), strpos($source, 'chown www-data:www-data /tmp/mysql-ca.pem'));
    }

    public function test_file_cache_retains_counters_between_independent_store_instances(): void
    {
        $this->assertStringContainsString("'default' => env('CACHE_DRIVER', 'file')", $this->source('config/cache.php'));
        $this->assertStringContainsString('storage/framework/cache/data', $this->source('cloudflare/start-container.sh'));
        // Probe a named file, like FileStore uses. WASM tmpfile() can report
        // success even when locks on host-mounted named files are unsupported.
        $probePath = sys_get_temp_dir().'/clapslearn-lock-probe-'.bin2hex(random_bytes(8));
        file_put_contents($probePath, 'probe');
        $probe = fopen($probePath, 'rb');
        $this->assertNotFalse($probe);
        $supportsLocks = flock($probe, LOCK_SH);
        if ($supportsLocks) {
            flock($probe, LOCK_UN);
        }
        fclose($probe);
        unlink($probePath);
        if (! $supportsLocks) {
            $this->markTestSkipped('Native filesystem locking is required; PHP-WASM cannot validate file-cache persistence.');
        }

        $files = new \Illuminate\Filesystem\Filesystem();
        $path = sys_get_temp_dir().'/clapslearn-cache-test-'.bin2hex(random_bytes(8));
        try {
            $first = new \Illuminate\Cache\FileStore($files, $path);
            $first->put('attempt-counter', 1, 60);
            unset($first);
            $second = new \Illuminate\Cache\FileStore($files, $path);
            $this->assertSame(1, $second->get('attempt-counter'));
            $this->assertSame(2, $second->increment('attempt-counter'));
            $third = new \Illuminate\Cache\FileStore($files, $path);
            $this->assertSame(2, $third->get('attempt-counter'));
        } finally {
            $files->deleteDirectory($path);
        }
    }
}
