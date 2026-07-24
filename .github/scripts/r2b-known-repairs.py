from pathlib import Path


def replace(path: str, old: str, new: str) -> None:
    file = Path(path)
    source = file.read_text()
    if old not in source:
        raise RuntimeError(f"Expected pattern missing in {path}: {old[:180]!r}")
    file.write_text(source.replace(old, new))


# MySQL SSL options must use the PDO MySQL driver constant. The previous
# generic PDO constant does not exist and prevented Laravel from booting during
# Composer package discovery.
replace(
    "backend/config/database.php",
    "PDO::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),",
    "PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),",
)

# The standard Laravel controller base class was missing even though every
# HTTP controller imports and extends it. Restore the minimal framework
# boundary rather than modifying every controller independently.
controller = Path("backend/app/Http/Controllers/Controller.php")
if controller.exists():
    raise RuntimeError("Controller.php unexpectedly already exists")
controller.write_text(
    """<?php

namespace App\\Http\\Controllers;

abstract class Controller
{
    // Shared HTTP controller boundary for the Rosta API.
}
"""
)

# CI must provide an ephemeral process-level application key before Composer
# scripts boot Laravel. It must not mutate .env through artisan key:generate.
workflow = ".github/workflows/backend-ci.yml"
replace(
    workflow,
    '''      APP_URL: http://localhost:8000
      FRONTEND_ALLOWED_ORIGINS: http://localhost:5173,http://127.0.0.1:5173
''',
    '''      APP_URL: http://localhost:8000
      LOG_CHANNEL: stack
      LOG_STACK: single
      FRONTEND_ALLOWED_ORIGINS: http://localhost:5173,http://127.0.0.1:5173
''',
)
replace(
    workflow,
    '''      - name: Require deterministic dependency lock
        run: test -s composer.lock
''',
    '''      - name: Create ephemeral application key
        shell: bash
        run: |
          set -Eeuo pipefail
          echo "APP_KEY=base64:$(php -r 'echo base64_encode(random_bytes(32));')" >> "$GITHUB_ENV"

      - name: Require deterministic dependency lock
        run: test -s composer.lock
''',
)
replace(
    workflow,
    '''      - name: Generate application key
        run: php artisan key:generate --force

''',
    '',
)

# Shared runtime route-contract support. Grouped prefixes and inherited
# middleware must be evaluated from Laravel's registered route collection, not
# guessed from source-code concatenation.
route_support = Path("backend/scripts/route-contract-support.php")
if route_support.exists():
    raise RuntimeError("route-contract-support.php unexpectedly already exists")
route_support.write_text(
    """<?php

use Illuminate\\Contracts\\Console\\Kernel;
use Illuminate\\Routing\\Route as LaravelRoute;

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
"""
)

# Business audit: replace source-fragment route checks with exact registered
# routes and inherited middleware checks.
replace(
    "backend/scripts/audit-business-contract.php",
    '''$routes = $read('routes/api.php');
foreach ([
    '/seller/origins',
    '/media',
    'roast-batches',
    'stock-ledger',
    '/admin/origins',
    '/cart/validate',
    '/checkout/quote',
    '/orders',
    '/orders/{orderId}/cancel',
    'throttle:cart-validate',
    'throttle:checkout-quote',
    'throttle:order-create',
] as $routeBoundary) {
    if (! str_contains($routes, $routeBoundary)) {
        $failures[] = 'Missing route boundary: '.$routeBoundary;
    }
}
foreach ([
    "['auth:sanctum', 'rosta.session']",
    'rosta.role:roastery_owner,roastery_manager,roastery_staff,administrator',
    'rosta.role:administrator',
] as $middlewareBoundary) {
    if (! str_contains($routes, $middlewareBoundary)) {
        $failures[] = 'Missing middleware boundary: '.$middlewareBoundary;
    }
}
''',
    '''require_once $root.'/scripts/route-contract-support.php';
$routeContracts = rostaRouteContracts($root);

foreach ([
    '/seller/origins',
    '/seller/roasteries/{roasteryId}/media',
    '/seller/roasteries/{roasteryId}/products/{productId}/roast-batches',
    '/seller/roasteries/{roasteryId}/variants/{variantId}/stock-ledger',
    '/admin/origins',
    '/cart/validate',
    '/checkout/quote',
    '/orders',
    '/orders/{orderId}/cancel',
] as $routeBoundary) {
    if (! isset($routeContracts[$routeBoundary])) {
        $failures[] = 'Missing registered route boundary: '.$routeBoundary;
    }
}

foreach ([
    '/cart/validate' => ['throttle:cart-validate'],
    '/checkout/quote' => ['auth:sanctum', 'rosta.session', 'throttle:checkout-quote'],
    '/orders' => ['auth:sanctum', 'rosta.session', 'throttle:order-create'],
    '/seller/origins' => ['auth:sanctum', 'rosta.session', 'rosta.role:roastery_owner,roastery_manager,roastery_staff,administrator'],
    '/admin/origins' => ['auth:sanctum', 'rosta.session', 'rosta.role:administrator'],
] as $path => $requiredMiddleware) {
    $registered = $routeContracts[$path]['middleware'] ?? [];
    foreach ($requiredMiddleware as $middlewareBoundary) {
        if (! in_array($middlewareBoundary, $registered, true)) {
            $failures[] = 'Missing registered middleware '.$middlewareBoundary.' for '.$path;
        }
    }
}
''',
)

# OpenAPI drift audit: keep exact OpenAPI checks and derive route truth from
# Laravel's runtime route collection.
replace(
    "backend/scripts/audit-commerce-openapi-drift.php",
    '''$routeSources = implode("\\n", array_map(
    static fn (string $file): string => file_get_contents($root.'/routes/'.$file),
    [
        'api.php',
        'payments.php',
        'fulfillment.php',
        'reviews-support.php',
        'media-uploads.php',
        'finance.php',
        'seller-bootstrap.php',
        'admin-operations.php',
    ],
));
''',
    '''require_once $root.'/scripts/route-contract-support.php';
$routeContracts = rostaRouteContracts($root);
''',
)
replace(
    "backend/scripts/audit-commerce-openapi-drift.php",
    '''    if (! str_contains($routeSources, $path)) {
        $missingRoutes[] = $path;
    }
''',
    '''    if (! isset($routeContracts[$path])) {
        $missingRoutes[] = $path;
    }
''',
)

# Validation audit: inspect only the pull_request trigger block. A main-only
# push trigger is valid and must not be misclassified as a PR restriction.
replace(
    "backend/scripts/audit-validation-hardening.php",
    '''$gates = [];
$gate = static function (string $name, bool $passed, string $evidence) use (&$gates): void {
    $gates[] = compact('name', 'passed', 'evidence');
};
''',
    '''$gates = [];
$gate = static function (string $name, bool $passed, string $evidence) use (&$gates): void {
    $gates[] = compact('name', 'passed', 'evidence');
};

$pullRequestBlock = '';
$insidePullRequest = false;
foreach (preg_split('/\\R/', $files['frontend_ci']) ?: [] as $line) {
    if (preg_match('/^([A-Za-z0-9_-]+):(?:\\s|$)/', $line, $match) === 1) {
        if ($match[1] === 'pull_request') {
            $insidePullRequest = true;
            continue;
        }
        if ($insidePullRequest) {
            break;
        }
    }
    if ($insidePullRequest) {
        $pullRequestBlock .= $line."\\n";
    }
}
''',
)
replace(
    "backend/scripts/audit-validation-hardening.php",
    '''    str_contains($files['frontend_ci'], "pull_request:\n")
        && ! str_contains($files['frontend_ci'], 'branches: [main]')
''',
    '''    str_contains($files['frontend_ci'], "pull_request:\n")
        && ! str_contains($pullRequestBlock, 'branches:')
''',
)

# Live-public review audit: validate the actual Form Request boundary and the
# controller's use of validated data, rather than requiring a field literal in
# the thin controller.
replace(
    "backend/scripts/audit-live-public-contract.php",
    '''    'review_controller' => file_get_contents($root.'/app/Http/Controllers/Reviews/ReviewController.php'),
''',
    '''    'review_controller' => file_get_contents($root.'/app/Http/Controllers/Reviews/ReviewController.php'),
    'review_request' => file_get_contents($root.'/app/Http/Requests/Reviews/CreateReviewRequest.php'),
''',
)
replace(
    "backend/scripts/audit-live-public-contract.php",
    '''        && str_contains($files['public_reviews'], 'publicForProduct')
        && str_contains($files['review_controller'], 'order_item_id'),
''',
    '''        && str_contains($files['public_reviews'], 'publicForProduct')
        && str_contains($files['review_controller'], 'CreateReviewRequest')
        && str_contains($files['review_controller'], '$request->validated()')
        && str_contains($files['review_request'], 'RejectsUnexpectedInput')
        && str_contains($files['review_request'], "'order_item_id'")
        && str_contains($files['review_request'], "'required', 'string'"),
''',
)
