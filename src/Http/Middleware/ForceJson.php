<?php

declare(strict_types=1);

namespace Atannex\Routing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Makes the request ask for JSON, so errors render as JSON (#[Json]). */
final class ForceJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
