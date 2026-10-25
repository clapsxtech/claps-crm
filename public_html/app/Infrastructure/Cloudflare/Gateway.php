<?php
namespace App\Infrastructure\Cloudflare;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class Gateway
{
    public function __construct(private string $url, private string $token) {}

    public function call(string $operation, array $payload = []): array
    {
        $response = Http::withToken($this->token)->acceptJson()->timeout(120)
            ->post(rtrim($this->url, '/').'/__cloudflare/database/'.$operation, $payload);
        if (!$response->successful()) {
            // Do not put SQL, bindings, credentials, or response bodies in application logs.
            throw new RuntimeException('Cloudflare database request failed (HTTP '.$response->status().').');
        }
        return $response->json();
    }
}
