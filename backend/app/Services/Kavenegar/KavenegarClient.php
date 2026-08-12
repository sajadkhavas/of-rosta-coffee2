<?php

namespace App\Services\Kavenegar;

use App\Exceptions\KavenegarDeliveryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class KavenegarClient
{
    /** @var list<int> */
    private const SAFE_RETRY_PROVIDER_STATUSES = [409, 429, 451];

    public function __construct(
        private readonly KavenegarCircuitBreaker $circuit,
    ) {}

    public function isConfigured(): bool
    {
        $apiKey = trim((string) config('rosta.kavenegar.api_key'));
        $baseUrl = rtrim(trim((string) config('rosta.kavenegar.base_url')), '/');
        $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        $path = rtrim((string) parse_url($baseUrl, PHP_URL_PATH), '/');

        if (
            $apiKey === ''
            || preg_match('/^[A-Za-z0-9_-]{16,200}$/', $apiKey) !== 1
            || $scheme !== 'https'
            || $host === ''
            || $path !== '/v1'
        ) {
            return false;
        }

        return ! app()->environment('production') || $host === 'api.kavenegar.com';
    }

    public function isAvailable(): bool
    {
        return $this->isConfigured() && ! $this->circuit->isOpen();
    }

    public function circuitRetryAfterSeconds(): int
    {
        return $this->circuit->retryAfterSeconds();
    }

    public function verifyLookup(
        string $receptor,
        string $token,
        string $template,
    ): string {
        return $this->request('verify/lookup.json', [
            'receptor' => $receptor,
            'token' => $token,
            'template' => $template,
            'type' => 'sms',
        ]);
    }

    public function sendMessage(
        string $receptor,
        string $message,
        string $sender,
    ): string {
        return $this->request('sms/send.json', [
            'receptor' => $receptor,
            'message' => $message,
            'sender' => $sender,
        ]);
    }

    /**
     * Kavenegar send operations have no provider idempotency key. This method
     * deliberately performs one HTTP attempt; only an explicit provider
     * rejection may be retried later by the durable caller.
     *
     * @param  array<string, string>  $payload
     */
    private function request(string $endpoint, array $payload): string
    {
        if (! $this->isConfigured()) {
            throw new KavenegarDeliveryException('configuration_invalid');
        }

        if ($this->circuit->isOpen()) {
            throw new KavenegarDeliveryException(
                'circuit_open',
                retryable: true,
                retryAfterSeconds: $this->circuit->retryAfterSeconds(),
            );
        }

        $apiKey = rawurlencode(trim((string) config('rosta.kavenegar.api_key')));
        $baseUrl = rtrim(trim((string) config('rosta.kavenegar.base_url')), '/');

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(max(1, (int) config('rosta.kavenegar.connect_timeout_seconds', 3)))
                ->timeout(max(2, (int) config('rosta.kavenegar.timeout_seconds', 8)))
                ->post("{$baseUrl}/{$apiKey}/{$endpoint}", $payload);
        } catch (ConnectionException) {
            $this->circuit->recordFailure();

            throw new KavenegarDeliveryException(
                'connection_outcome_unknown',
                ambiguous: true,
            );
        } catch (Throwable) {
            $this->circuit->recordFailure();

            throw new KavenegarDeliveryException(
                'transport_outcome_unknown',
                ambiguous: true,
            );
        }

        return $this->parseResponse($response);
    }

    private function parseResponse(Response $response): string
    {
        if ($response->status() === 429) {
            $this->circuit->recordFailure();

            throw new KavenegarDeliveryException(
                'http_rate_limited',
                retryable: true,
                retryAfterSeconds: $this->retryAfter($response),
            );
        }

        if ($response->serverError()) {
            $this->circuit->recordFailure();

            throw new KavenegarDeliveryException(
                'http_server_outcome_unknown',
                ambiguous: true,
            );
        }

        if (! $response->successful()) {
            throw new KavenegarDeliveryException('http_request_rejected');
        }

        $body = $response->json();
        if (! is_array($body)) {
            $this->circuit->recordFailure();

            throw new KavenegarDeliveryException(
                'response_malformed',
                ambiguous: true,
            );
        }

        $providerStatus = filter_var(
            data_get($body, 'return.status'),
            FILTER_VALIDATE_INT,
        );
        if ($providerStatus !== 200) {
            $retryable = is_int($providerStatus)
                && in_array($providerStatus, self::SAFE_RETRY_PROVIDER_STATUSES, true);
            if ($retryable) {
                $this->circuit->recordFailure();
            }

            throw new KavenegarDeliveryException(
                $retryable ? 'provider_temporarily_rejected' : 'provider_rejected',
                retryable: $retryable,
                retryAfterSeconds: $retryable ? $this->retryAfter($response) : null,
            );
        }

        $messageId = data_get($body, 'entries.0.messageid');
        if ((! is_string($messageId) && ! is_int($messageId)) || trim((string) $messageId) === '') {
            $this->circuit->recordFailure();

            throw new KavenegarDeliveryException(
                'success_identifier_missing',
                ambiguous: true,
            );
        }

        $this->circuit->recordSuccess();

        return trim((string) $messageId);
    }

    private function retryAfter(Response $response): int
    {
        $header = filter_var($response->header('Retry-After'), FILTER_VALIDATE_INT);
        $fallback = max(5, (int) config('rosta.kavenegar.retry_base_seconds', 30));

        return max(1, min(900, is_int($header) ? $header : $fallback));
    }
}
