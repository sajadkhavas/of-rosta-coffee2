<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Tests\TestCase;

final class RouteUniquenessContractTest extends TestCase
{
    public function test_named_routes_and_method_uri_pairs_are_unique(): void
    {
        $names = [];
        $methodUris = [];
        foreach (app('router')->getRoutes() as $route) {
            if (! $route instanceof Route) continue;
            $name = $route->getName();
            if (is_string($name) && $name !== '') {
                $this->assertArrayNotHasKey($name, $names, "Duplicate route name: {$name}");
                $names[$name] = true;
            }
            foreach (array_filter($route->methods(), static fn (string $method): bool => $method !== 'HEAD') as $method) {
                $key = $method.' '.$route->uri();
                $this->assertArrayNotHasKey($key, $methodUris, "Duplicate route method/URI: {$key}");
                $methodUris[$key] = true;
            }
        }
    }

    public function test_seller_roastery_bootstrap_is_the_single_get_contract(): void
    {
        $matches = [];
        foreach (app('router')->getRoutes() as $route) {
            if ($route instanceof Route && in_array('GET', $route->methods(), true) && $route->uri() === 'api/v1/seller/roasteries') $matches[] = $route;
        }
        $this->assertCount(1, $matches);
        $route = $matches[0];
        $this->assertSame('api.v1.seller.roasteries.bootstrap', $route->getName());
        $middleware = $route->gatherMiddleware();
        $this->assertContains('api', $middleware);
        $this->assertContains('throttle:api', $middleware);
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('rosta.session', $middleware);
        $this->assertFalse(collect($middleware)->contains(static fn (string $item): bool => str_starts_with($item, 'rosta.role:')));
    }
}
