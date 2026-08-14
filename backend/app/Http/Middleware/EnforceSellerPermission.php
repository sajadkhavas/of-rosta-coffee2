<?php

namespace App\Http\Middleware;

use App\Models\Roastery;
use App\Models\User;
use App\Services\Seller\SellerAccess;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceSellerPermission
{
    public function __construct(private readonly SellerAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $routeName = is_object($route) ? $route->getName() : null;
        if (! is_string($routeName) || ! str_starts_with($routeName, 'api.v1.seller.')) {
            return $next($request);
        }

        $roasteryId = $request->route('roasteryId');
        if (! is_string($roasteryId) || $roasteryId === '') {
            return $next($request);
        }

        $permission = $this->access->permissionForRoute($routeName);
        if ($permission === null) {
            throw new AuthorizationException;
        }

        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        $roastery = Roastery::query()->find($roasteryId);
        if (! $roastery instanceof Roastery) {
            throw (new ModelNotFoundException)->setModel(Roastery::class, [$roasteryId]);
        }

        $this->access->assertPermission($user, $roastery, $permission);

        return $next($request);
    }
}
