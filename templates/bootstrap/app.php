<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api1.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->api(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) =>
                $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => __('messages.validation_failed'),
                'status_code' => 422,
                'data' => $exception->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => __('messages.unauthorized'),
                'status_code' => 401,
                'data' => [],
            ], 401);
        });

        $exceptions->render(function (UnauthorizedException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => __('messages.unauthorized'),
                'status_code' => 403,
                'data' => [],
            ], 403);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface) {
                return response()->json([
                    'message' => $exception->getMessage() ?: __('messages.not_found'),
                    'status_code' => $exception->getStatusCode(),
                    'data' => [],
                ], $exception->getStatusCode());
            }

            return response()->json([
                'message' => __('messages.server_error'),
                'status_code' => 500,
                'data' => [],
            ], 500);
        });
    })
    ->create();
