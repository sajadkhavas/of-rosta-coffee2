<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $candidate = trim((string) $request->header('X-Request-ID'));
        $requestId = preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $candidate) === 1
            ? $candidate
            : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set(
            'X-Rosta-Contract-Version',
            (string) config('rosta.contract_version'),
        );

        return $response;
    }
}
