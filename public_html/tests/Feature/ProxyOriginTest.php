<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\CreatesApplication;

class ProxyOriginTest extends TestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        config(['proxy.trusted_proxies' => '', 'proxy.public_origin' => '', 'app.asset_url' => null]);
        Route::get('/_proxy-origin-test', function (Request $request) {
            return response()->json([
                'origin' => $request->getSchemeAndHttpHost(),
                'asset' => asset('css/app.css'),
                'form' => route('login'),
                'ip' => $request->ip(),
                'forwarded_host' => $request->headers->get('x-forwarded-host'),
                'forwarded' => $request->headers->get('forwarded'),
            ]);
        });
        Route::get('/_proxy-redirect-test', fn () => redirect()->route('login'));
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        parent::tearDown();
    }

    public function test_worker_origin_restores_https_without_trusting_the_transport_ip(): void
    {
        config(['proxy.public_origin' => 'https://logs.example.test']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.30'])
            ->withHeaders(['X-Forwarded-Host' => 'logs.example.test', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
            ->get('http://logs.example.test/_proxy-origin-test')
            ->assertOk()->assertJson([
                'origin' => 'https://logs.example.test',
                'asset' => 'https://logs.example.test/css/app.css',
                'form' => 'https://logs.example.test/login',
                'ip' => '192.0.2.30',
                'forwarded_host' => null,
            ]);
    }

    public function test_real_login_form_and_assets_use_the_public_https_origin(): void
    {
        config(['proxy.public_origin' => 'https://logs.example.test']);
        $this->get('http://logs.example.test/login')->assertOk()
            ->assertSee('action="https://logs.example.test/login"', false)
            ->assertSee('https://logs.example.test/css/app.css', false)
            ->assertDontSee('http://container', false);
        $this->get('http://logs.example.test/_proxy-redirect-test')
            ->assertRedirect('https://logs.example.test/login');
    }

    public function test_spoofed_forwarding_headers_cannot_override_the_canonical_origin(): void
    {
        config(['proxy.public_origin' => 'https://logs.example.test']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.30'])
            ->withHeaders([
                'Forwarded' => 'for=203.0.113.99;host=evil.example;proto=http',
                'X-Forwarded-Host' => 'evil.example', 'X-Forwarded-Proto' => 'http',
                'X-Forwarded-Port' => '81', 'X-Forwarded-For' => '203.0.113.99',
                'X-Forwarded-Prefix' => '/evil',
            ])->get('http://logs.example.test/_proxy-origin-test')
            ->assertOk()->assertJson([
                'origin' => 'https://logs.example.test', 'form' => 'https://logs.example.test/login',
                'ip' => '192.0.2.30', 'forwarded_host' => null, 'forwarded' => null,
            ]);
    }

    public function test_mismatched_host_cannot_activate_the_configured_https_origin(): void
    {
        config(['proxy.public_origin' => 'https://logs.example.test']);
        $this->withHeaders(['X-Forwarded-Host' => 'logs.example.test', 'X-Forwarded-Proto' => 'https'])
            ->get('http://other.example.test/_proxy-origin-test')
            ->assertOk()->assertJson(['origin' => 'http://other.example.test', 'form' => 'http://other.example.test/login']);
    }

    public function test_untrusted_headers_are_ignored_when_no_public_origin_is_configured(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.30'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example', 'X-Forwarded-For' => '203.0.113.99'])
            ->get('http://local.example.test/_proxy-origin-test')
            ->assertOk()->assertJson(['origin' => 'http://local.example.test', 'ip' => '192.0.2.30']);
    }

    public function test_only_configured_proxy_cidrs_can_forward_scheme_port_and_client_ip(): void
    {
        config(['proxy.trusted_proxies' => '10.20.30.0/24, 2001:db8:1234::/64']);
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.10'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '8443',
                'X-Forwarded-For' => '203.0.113.99', 'X-Forwarded-Host' => 'evil.example',
            ])->get('http://logs.example.test/_proxy-origin-test')
            ->assertOk()->assertJson([
                'origin' => 'https://logs.example.test:8443', 'ip' => '203.0.113.99', 'forwarded_host' => null,
            ]);
    }

    public function test_unlisted_proxy_cannot_forward_scheme_even_when_other_proxies_are_trusted(): void
    {
        config(['proxy.trusted_proxies' => '10.20.30.0/24']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.30'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
            ->get('http://logs.example.test/_proxy-origin-test')
            ->assertOk()->assertJson(['origin' => 'http://logs.example.test']);
    }

    public function test_canonical_origin_handles_an_explicit_https_port(): void
    {
        config(['proxy.public_origin' => 'https://logs.example.test:8443']);
        $this->get('http://logs.example.test:8443/_proxy-origin-test')
            ->assertOk()->assertJson(['origin' => 'https://logs.example.test:8443', 'form' => 'https://logs.example.test:8443/login']);
    }

    /** @dataProvider invalidProxyConfiguration */
    public function test_invalid_and_wildcard_proxy_configuration_fails_closed(string $proxies): void
    {
        config(['proxy.trusted_proxies' => $proxies]);
        $this->withoutExceptionHandling();
        $this->expectException(\InvalidArgumentException::class);
        $this->get('http://logs.example.test/_proxy-origin-test');
    }

    public function invalidProxyConfiguration(): array
    {
        return array_map(fn ($value) => [$value], ['*', '**', '0.0.0.0/0', '::/0', '10.0.0.1/33', '::1/129', 'proxy.local', '10.0.0.1/no', '10.0.0.1/24/4']);
    }

    /** @dataProvider invalidPublicOrigins */
    public function test_invalid_public_origin_fails_closed(string $origin): void
    {
        config(['proxy.public_origin' => $origin]);
        $this->withoutExceptionHandling();
        $this->expectException(\InvalidArgumentException::class);
        $this->get('http://logs.example.test/_proxy-origin-test');
    }

    public function invalidPublicOrigins(): array
    {
        return array_map(fn ($value) => [$value], ['http://logs.example.test', 'https://logs.example.test/path', 'https://user@logs.example.test', 'https://logs.example.test?x=1', 'https://logs.example.test#fragment', 'https://not a host.test', 'https://logs.example.test:0']);
    }
}
