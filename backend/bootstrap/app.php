<?php

use App\Exceptions\ApiDomainException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnforceSellerPermission;
use App\Http\Middleware\EnsureActiveAuthSession;
use App\Http\Middleware\RequireAnyRole;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$copyHeaders = static function (array $headers, JsonResponse $response): JsonResponse {
    foreach ($headers as $name => $value) {
        $response->headers->set((string) $name, $value);
    }

    return $response;
};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->statefulApi();
        $middleware->alias([
            'rosta.session' => EnsureActiveAuthSession::class,
            'rosta.role' => RequireAnyRole::class,
            'rosta.seller' => EnforceSellerPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($copyHeaders): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) use ($copyHeaders) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $requestId = $request->attributes->get('request_id');
            $safeRequestId = is_string($requestId) ? $requestId : null;

            if ($exception instanceof ApiDomainException) {
                $response = ApiResponse::error(
                    $exception->errorCode,
                    $exception->getMessage(),
                    $exception->status,
                    $exception->fields,
                    $safeRequestId,
                );

                return $copyHeaders($exception->headers, $response);
            }

            if ($exception instanceof ValidationException) {
                return ApiResponse::error(
                    'request.validation_failed',
                    'اطلاعات ارسال‌شده معتبر نیست.',
                    422,
                    $exception->errors(),
                    $safeRequestId,
                );
            }

            if ($exception instanceof TokenMismatchException) {
                return ApiResponse::error(
                    'auth.csrf_expired',
                    'نشست امن منقضی شده است. درخواست را دوباره ارسال کنید.',
                    419,
                    requestId: $safeRequestId,
                );
            }

            if ($exception instanceof AuthenticationException) {
                return ApiResponse::error(
                    'request.unauthenticated',
                    'نشست کاربری معتبر نیست.',
                    401,
                    requestId: $safeRequestId,
                );
            }

            if ($exception instanceof AuthorizationException) {
                return ApiResponse::error(
                    'request.forbidden',
                    'اجازه انجام این عملیات را ندارید.',
                    403,
                    requestId: $safeRequestId,
                );
            }

            if ($exception instanceof ModelNotFoundException) {
                return ApiResponse::error(
                    'request.not_found',
                    'منبع درخواستی پیدا نشد.',
                    404,
                    requestId: $safeRequestId,
                );
            }

            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $code = match ($status) {
                    401 => 'request.unauthenticated',
                    403 => 'request.forbidden',
                    404 => 'request.not_found',
                    405 => 'request.method_not_allowed',
                    419 => 'auth.csrf_expired',
                    429 => 'request.rate_limited',
                    default => $status >= 500 ? 'server.unavailable' : 'request.failed',
                };

                $response = ApiResponse::error(
                    $code,
                    $status >= 500
                        ? 'سرویس موقتاً در دسترس نیست.'
                        : ($exception->getMessage() ?: 'درخواست انجام نشد.'),
                    $status,
                    requestId: $safeRequestId,
                );

                return $copyHeaders($exception->getHeaders(), $response);
            }

            report($exception);

            return ApiResponse::error(
                'server.unavailable',
                'سرویس موقتاً در دسترس نیست.',
                500,
                requestId: $safeRequestId,
            );
        });
    })
    ->create();
