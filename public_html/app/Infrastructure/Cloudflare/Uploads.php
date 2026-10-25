<?php
namespace App\Infrastructure\Cloudflare;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class Uploads
{
    public static function enabled(): bool { return config('database.default') === 'd1'; }
    public static function put(string $key, UploadedFile $file): void
    {
        $response = Http::withToken(config('database.connections.d1.token'))->timeout(60)
            ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType())
            ->put(rtrim(config('database.connections.d1.gateway'), '/').'/__cloudflare/uploads/'.$key);
        if (!$response->successful()) throw new RuntimeException('Unable to store profile image in R2.');
    }
    public static function delete(string $key): void
    {
        // Cleanup is best effort after a committed profile update.
        try { Http::withToken(config('database.connections.d1.token'))->timeout(20)
            ->delete(rtrim(config('database.connections.d1.gateway'), '/').'/__cloudflare/uploads/'.$key); }
        catch (\Throwable) { report(new RuntimeException('R2 profile cleanup needs retry.')); }
    }
}
