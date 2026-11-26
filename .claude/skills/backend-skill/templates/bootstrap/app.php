<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
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

        // Baseline rate limiting (references/api-standards.md, Rate
        // Limiting): every /api/v1 route is throttled by the named `api`
        // limiter defined in AppServiceProvider::boot() — per authenticated
        // user id, or per IP for guests.
        $middleware->throttleApi();

        // Laravel's default middleware priority places the AuthenticatesRequests
        // contract (implemented by the auth middleware) before ThrottleRequests,
        // so the auth middleware rejects unauthenticated requests with 401
        // before the `api` baseline limiter ever counts them — leaving
        // protected /api/v1 routes unthrottled for guests. Moving an entry
        // that the default list already contains (ThrottleRequests) cannot be
        // done with relative prepend/append calls — they are skipped when the
        // middleware is already listed — so the full default order is replaced
        // with the two throttle middlewares moved above the auth contract.
        // The default guard is the Passport `api` guard (config/auth.php), so
        // the limiter still keys per authenticated user id. Approved limits
        // stay unchanged.
        $middleware->priority([
            // Localization must resolve before any middleware that can
            // terminate the request with an envelope message (auth 401,
            // throttled 429): the priority list sorts every listed
            // middleware ahead of the unlisted ones, so SetLocale — which
            // only rides along in the api group — has to be first, or the
            // auth and throttle middlewares would answer before the
            // request's locale is applied.
            SetLocale::class,
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Default projects carry no translation files: the envelope messages
        // are English literals handled here directly
        // (references/localization.md). When the project requirements
        // explicitly request additional languages, these literals become
        // translation-key calls backed by lang/{locale}/messages.php.
        // The throttled /oauth/token endpoint lives outside the api/v1
        // prefix; its exceptions (most importantly the 429 rate-limit
        // response) must render as JSON for API clients exactly like the
        // api/v1 responses do, instead of Laravel's HTML error page.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) =>
                $request->is('api/*') || $request->is('oauth/*') || $request->expectsJson()
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->is('oauth/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => 'The provided data is invalid.',
                'status_code' => 422,
                'data' => $exception->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->is('oauth/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => 'You are not authorized to perform this action.',
                'status_code' => 401,
                'data' => [],
            ], 401);
        });

        $exceptions->render(function (UnauthorizedException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->is('oauth/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => 'You are not authorized to perform this action.',
                'status_code' => 403,
                'data' => [],
            ], 403);
        });

        // Signed-URL rejections (references/authentication.md, Email
        // Verification): the ValidateSignature middleware on the
        // verification route throws InvalidSignatureException for an
        // expired (60 minutes) or tampered verification link. The native
        // exception's English message must not leak; the approved envelope
        // with the verification-link message answers instead.
        $exceptions->render(function (InvalidSignatureException $exception, Request $request) {
            if (! ($request->is('api/*') || $request->is('oauth/*') || $request->expectsJson())) {
                return null;
            }

            return response()->json([
                'message' => 'This verification link is invalid or has expired.',
                'status_code' => 403,
                'data' => [],
            ], 403);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! ($request->is('api/*') || $request->is('oauth/*') || $request->expectsJson())) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface) {
                $statusCode = $exception->getStatusCode();

                // Rate-limit rejections (HTTP 429 from the throttle
                // middleware, references/api-standards.md, Rate Limiting)
                // answer with the rate-limit message, and their throttle
                // headers (Retry-After, X-RateLimit-*) must reach
                // the client so it knows when to retry. getHeaders() returns
                // an empty array for every other HttpException.
                $message = $statusCode === 429
                    ? 'Too many attempts. Please try again later.'
                    : ($exception->getMessage() ?: 'The requested resource was not found.');

                return response()->json([
                    'message' => $message,
                    'status_code' => $statusCode,
                    'data' => [],
                ], $statusCode)->withHeaders($exception->getHeaders());
            }

            return response()->json([
                'message' => 'An unexpected error occurred.',
                'status_code' => 500,
                'data' => [],
            ], 500);
        });
    })
    ->create();
