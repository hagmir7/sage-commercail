<?php

use App\Notifications\ErrorOccurred;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Force JSON negotiation on every API request, even if the
        // client forgot the "Accept: application/json" header.
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // ---- Reporting (unchanged) ----
        $exceptions->reportable(function (\Throwable $e) {
            if (app()->environment('production')) {
                Notification::route('slack', env('SLACK_WEBHOOK_URL'))
                    ->notify(new ErrorOccurred($e));
            }
        });

        // ---- Rendering: always JSON ----
        $exceptions->shouldRenderJsonWhen(fn (Request $request, \Throwable $e) => true);

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            return response()->json(['message' => 'Resource not found'], 404);
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            // Keep Laravel's standard 422 payload (message + errors)
            if ($e instanceof ValidationException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            $message = ($status < 500 || config('app.debug'))
                ? ($e->getMessage() ?: 'Error')
                : 'Server error';

            return response()->json(['message' => $message], $status);
        });
    })
    ->create();