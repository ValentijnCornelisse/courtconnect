<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RolMiddleware
{
    public function handle(Request $verzoek, Closure $volgende, string $rol): Response
    {
        abort_unless($verzoek->user()?->rol === $rol, 403);

        return $volgende($verzoek);
    }
}
