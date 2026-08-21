<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Tests\TestCase;

final class ApiIntegrationContractTest extends TestCase
{
    public function test_every_versioned_api_route_has_exactly_one_baseline_api_throttle(): void
    {
        $checked = 0;

        foreach (app('router')->getRoutes() as $route) {
            if (! $route instanceof Route || ! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }

            $checked++;
            $middleware = $route->gatherMiddleware();

            $this->assertContains('api', $middleware, "Missing api middleware on {$route->uri()}");
            $this->assertSame(
                1,
                count(array_filter(
                    $middleware,
                    static fn (string $entry): bool => $entry === 'throttle:api',
                )),
                "Expected exactly one baseline API throttle on {$route->uri()}",
            );
        }

        $this->assertGreaterThan(0, $checked, 'No versioned API routes were discovered.');
    }

    public function test_zarinpal_return_contract_is_get_and_no_generic_post_callback_exists(): void
    {
        $zarinpalMethods = [];
        $genericCallbackMethods = [];

        foreach (app('router')->getRoutes() as $route) {
            if (! $route instanceof Route) {
                continue;
            }

            if ($route->uri() === 'api/v1/payments/zarinpal/callback/{paymentId}') {
                $zarinpalMethods = array_values(array_filter(
                    $route->methods(),
                    static fn (string $method): bool => $method !== 'HEAD',
                ));
            }

            if ($route->uri() === 'api/v1/payments/callback') {
                $genericCallbackMethods = array_merge(
                    $genericCallbackMethods,
                    array_filter(
                        $route->methods(),
                        static fn (string $method): bool => $method !== 'HEAD',
                    ),
                );
            }
        }

        $this->assertSame(['GET'], $zarinpalMethods);
        $this->assertSame([], array_values(array_unique($genericCallbackMethods)));
    }

    public function test_core_openapi_operations_exist_in_runtime_with_the_same_method(): void
    {
        $spec = file_get_contents(dirname(__DIR__, 3).'/docs/openapi/rosta-v1.yaml');
        $this->assertIsString($spec);

        $documented = $this->openApiOperations($spec);
        $runtime = $this->runtimeOperations();

        $this->assertNotSame([], $documented, 'No OpenAPI operations were discovered.');

        foreach (array_keys($documented) as $operation) {
            $this->assertArrayHasKey(
                $operation,
                $runtime,
                "OpenAPI documents an operation that is not registered at runtime: {$operation}",
            );
        }
    }

    public function test_core_openapi_describes_the_real_sanctum_cookie_contract(): void
    {
        $spec = file_get_contents(dirname(__DIR__, 3).'/docs/openapi/rosta-v1.yaml');
        $this->assertIsString($spec);

        $this->assertStringContainsString('name: rosta-session', $spec);
        $this->assertStringNotContainsString('name: laravel_session', $spec);
        $this->assertStringContainsString('SESSION_COOKIE', $spec);
    }

    public function test_provider_endpoint_defaults_match_audited_official_contracts(): void
    {
        $backendRoot = dirname(__DIR__, 2);
        $rostaConfig = file_get_contents($backendRoot.'/config/rosta.php');
        $kavenegarClient = file_get_contents($backendRoot.'/app/Services/Kavenegar/KavenegarClient.php');

        $this->assertIsString($rostaConfig);
        $this->assertIsString($kavenegarClient);

        $this->assertStringContainsString(
            'https://payment.zarinpal.com/pg/v4/payment/request.json',
            $rostaConfig,
        );
        $this->assertStringContainsString(
            'https://payment.zarinpal.com/pg/v4/payment/verify.json',
            $rostaConfig,
        );
        $this->assertStringContainsString(
            'https://payment.zarinpal.com/pg/StartPay',
            $rostaConfig,
        );
        $this->assertStringNotContainsString('https://api.zarinpal.com/', $rostaConfig);
        $this->assertStringNotContainsString('https://www.zarinpal.com/pg/StartPay', $rostaConfig);

        $this->assertStringContainsString('https://api.kavenegar.com/v1', $rostaConfig);
        $this->assertStringContainsString("'verify/lookup.json'", $kavenegarClient);
        $this->assertStringContainsString("'sms/send.json'", $kavenegarClient);
    }

    /**
     * @return array<string, true>
     */
    private function runtimeOperations(): array
    {
        $operations = [];

        foreach (app('router')->getRoutes() as $route) {
            if (! $route instanceof Route || ! str_starts_with($route->uri(), 'api/v1')) {
                continue;
            }

            $relative = substr($route->uri(), strlen('api/v1'));
            $path = '/'.ltrim($relative, '/');
            if ($path === '/') {
                continue;
            }

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $operations[strtoupper($method).' '.$path] = true;
            }
        }

        return $operations;
    }

    /**
     * @return array<string, true>
     */
    private function openApiOperations(string $spec): array
    {
        $operations = [];
        $currentPath = null;

        foreach (preg_split('/\R/', $spec) ?: [] as $line) {
            if (preg_match('/^  (\/[^:]+):\s*$/', $line, $pathMatch) === 1) {
                $currentPath = $pathMatch[1];

                continue;
            }

            if (
                $currentPath !== null
                && preg_match('/^    (get|post|put|patch|delete|options|head|trace):\s*$/i', $line, $methodMatch) === 1
            ) {
                $operations[strtoupper($methodMatch[1]).' '.$currentPath] = true;
            }
        }

        return $operations;
    }
}
