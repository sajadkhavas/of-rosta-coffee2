<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class VerifyCarrierWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('rosta.carrier.webhook_secret', '');
        $timestamp = trim((string) $request->header('X-Rosta-Carrier-Timestamp', ''));
        $eventId = trim((string) $request->header('X-Rosta-Carrier-Event-Id', ''));
        $provided = strtolower(trim((string) $request->header('X-Rosta-Carrier-Signature', '')));

        if (
            $secret === ''
            || $timestamp === ''
            || $eventId === ''
            || ! ctype_digit($timestamp)
            || ! preg_match('/^[A-Za-z0-9._:-]{12,160}$/', $eventId)
            || ! str_starts_with($provided, 'v1=')
        ) {
            throw new AccessDeniedHttpException('Invalid carrier webhook signature.');
        }

        $tolerance = max(30, min(1800, (int) config('rosta.carrier.webhook_tolerance_seconds', 300)));
        if (abs(now()->timestamp - (int) $timestamp) > $tolerance) {
            throw new AccessDeniedHttpException('Expired carrier webhook signature.');
        }

        $canonical = 'v1:'.$timestamp.':'.$eventId.':'.$request->getContent();
        $expected = hash_hmac('sha256', $canonical, $secret);
        $signature = substr($provided, 3);
        if (strlen($signature) !== 64 || ! ctype_xdigit($signature) || ! hash_equals($expected, $signature)) {
            throw new AccessDeniedHttpException('Invalid carrier webhook signature.');
        }

        $request->attributes->set('carrier_event_id', $eventId);
        $request->attributes->set('carrier_event_timestamp', (int) $timestamp);

        return $next($request);
    }
}
