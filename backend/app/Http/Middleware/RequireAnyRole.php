<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireAnyRole
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles,
    ): Response {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        $allowed = array_values(array_unique(array_filter(array_map(
            static fn (string $role): string => trim($role),
            $roles,
        ))));

        if ($allowed === [] || array_intersect($allowed, $user->roleNames()) === []) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
