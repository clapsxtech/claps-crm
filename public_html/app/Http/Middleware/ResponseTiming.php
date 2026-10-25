<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

/** Timing only: no query text, account identifiers or credentials in headers. */
class ResponseTiming
{
    public function handle(Request $request, Closure $next)
    {
        $started = microtime(true);
        $response = $next($request);
        $response->headers->set('Server-Timing', 'app;dur='.round((microtime(true)-$started)*1000, 1));
        return $response;
    }
}
