<?php

use App\Http\Middleware\RolMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['rol' => RolMiddleware::class]);
        $middleware->redirectUsersTo(fn (Request $verzoek) => route($verzoek->user()?->rol === 'beheerder' ? 'beheer.weekrooster' : 'velden'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['wachtwoord', 'wachtwoord_confirmation']);
        $exceptions->respond(function (Response $antwoord) {
            if ($antwoord->getStatusCode() >= 500) {
                return response()->view('errors.500', [], $antwoord->getStatusCode());
            }

            return $antwoord;
        });
    })->create();
