<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;

final class PassThrough
{
    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }
}
