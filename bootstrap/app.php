<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {

    $exceptions->shouldRenderJsonWhen(
        function ($request, $exception) {
            return $request->is('api/*')
                || $request->expectsJson();
        }
    );

    $exceptions->render(
        function (
            \Throwable $exception,
            \Illuminate\Http\Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof
                \Illuminate\Validation\ValidationException) {

                return response()->json([
                    'success' => false,
                    'message' => 'بيانات الإدخال غير صحيحة.',
                    'errors' => $exception->errors(),
                ], 422);
            }

            $status = match (true) {
                $exception instanceof
                    \Illuminate\Auth\AuthenticationException => 401,

                $exception instanceof
                    \Illuminate\Auth\Access\AuthorizationException => 403,

                $exception instanceof
                    \Illuminate\Database\Eloquent\ModelNotFoundException => 404,

                $exception instanceof
                    \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    => $exception->getStatusCode(),

                default => 500,
            };

            $message = match ($status) {
                401 => 'يجب تسجيل الدخول للوصول إلى هذه الخدمة.',
                403 => 'ليس لديك صلاحية لتنفيذ هذا الإجراء.',
                404 => 'المورد المطلوب غير موجود.',
                405 => 'طريقة الطلب غير مسموحة.',
                422 => 'بيانات الإدخال غير صحيحة.',
                429 => 'عدد الطلبات كبير، يرجى المحاولة لاحقاً.',
                default => 'حدث خطأ أثناء معالجة الطلب.',
            };

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => [],
            ], $status);
        }
    );
})
->create();
