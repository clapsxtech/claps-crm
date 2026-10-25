<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\IpUtils;

class TrustProxies extends Middleware
{
    /**
     * Host and path overrides are deliberately not trusted. The Worker preserves
     * the public Host even though getTcpPort carries the request over HTTP.
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO;

    public function handle(Request $request, Closure $next)
    {
        $proxies = $this->validatedProxies(config('proxy.trusted_proxies', ''));
        Request::setTrustedProxies($proxies, $this->headers);

        $remote = $request->server->get('REMOTE_ADDR', '');
        $trusted = filter_var($remote, FILTER_VALIDATE_IP) !== false
            && $proxies !== [] && IpUtils::checkIp($remote, $proxies);

        foreach (array_keys($request->headers->all()) as $header) {
            if ($header === 'forwarded' || $header === 'x-forwarded-host'
                || $header === 'x-forwarded-prefix' || $header === 'x-original-url'
                || $header === 'x-rewrite-url' || $header === 'x-real-ip'
                || (! $trusted && strpos($header, 'x-forwarded-') === 0)) {
                $request->headers->remove($header);
            }
        }

        $this->applyCanonicalOrigin($request);

        return $next($request);
    }

    private function validatedProxies($configured): array
    {
        $proxies = is_array($configured) ? $configured : explode(',', (string) $configured);
        $validated = [];

        foreach ($proxies as $proxy) {
            $proxy = trim((string) $proxy);
            if ($proxy === '') {
                continue;
            }

            $parts = explode('/', $proxy);
            $ip = $parts[0];
            $validIp = filter_var($ip, FILTER_VALIDATE_IP) !== false;
            $maxMask = strpos($ip, ':') === false ? 32 : 128;
            $validMask = count($parts) === 1 || (count($parts) === 2
                && ctype_digit($parts[1]) && (int) $parts[1] > 0 && (int) $parts[1] <= $maxMask);

            if (! $validIp || ! $validMask) {
                throw new InvalidArgumentException('TRUSTED_PROXIES must contain explicit IP addresses or nonzero CIDRs.');
            }

            $validated[] = $proxy;
        }

        return $validated;
    }

    private function applyCanonicalOrigin(Request $request): void
    {
        $origin = trim((string) config('proxy.public_origin', ''));
        if ($origin === '') {
            return;
        }

        $parts = parse_url($origin);
        if ($parts === false || filter_var($origin, FILTER_VALIDATE_URL) === false
            || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query'])
            || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] < 1)
            || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new InvalidArgumentException('PUBLIC_ORIGIN must be an HTTPS origin without a path, credentials, query or fragment.');
        }

        $port = $parts['port'] ?? 443;
        $authority = strtolower($parts['host']).($port === 443 ? '' : ':'.$port);
        // Read the actual Host, never getHost()/getHttpHost() derived from proxy headers.
        $host = strtolower((string) $request->headers->get('host', ''));
        if ($host !== $authority && ! ($port === 443 && $host === $authority.':443')) {
            return;
        }

        // Only deployment configuration can restore TLS terminated at the edge.
        // Removing these headers also prevents a trusted proxy from overriding it.
        $request->headers->remove('x-forwarded-proto');
        $request->headers->remove('x-forwarded-port');
        $request->server->set('HTTPS', 'on');
        $request->server->set('REQUEST_SCHEME', 'https');
        $request->server->set('SERVER_PORT', $port);
    }
}
