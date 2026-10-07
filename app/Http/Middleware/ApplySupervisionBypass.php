<?php

namespace App\Http\Middleware;

use App\Services\Supervision;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Applique un bypass superviseur à usage unique à la requête rejouée. */
class ApplySupervisionBypass
{
    public function __construct(protected Supervision $supervision)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $this->supervision->applyBypass($request);
        }

        return $next($request);
    }
}
