<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Route as LaravelRoute;

/**
 * @return array<string, array{methods: list<string>, middleware: list<string>}>
 */
function rostaRouteContracts(string $root): array
{
    require_once $root.'/vendor/autoload.php';

    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $contracts = [];
    foreach ($app['router']->getRoutes() as $route) {
        if (! $route instanceof LaravelRoute) {
            continue;
        }

        $path = '/'.ltrim($route->uri(), '/');
        foreach (['/api/v1', '/v1'] as $prefix) {
            if ($path === $prefix) {
                $path = '/';
                break;
            }
            if (str_starts_with($path, $prefix.'/')) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        $existing = $contracts[$path] ?? ['methods' => [], 'middleware' => []];
        $methods = array_values(array_filter(
            array_map('strval', $route->methods()),
            static fn (string $method): bool => $method !== 'HEAD',
        ));
        $middleware = array_values(array_map('strval', $route->gatherMiddleware()));

        $contracts[$path] = [
            'methods' => array_values(array_unique(array_merge($existing['methods'], $methods))),
            'middleware' => array_values(array_unique(array_merge($existing['middleware'], $middleware))),
        ];
    }

    ksort($contracts);

    return $contracts;
}
