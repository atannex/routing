<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Atannex\Routing\Http\Middleware\ForceJson;
use Attribute;

/**
 * Treats every request as wanting JSON (Accept: application/json), so
 * validation errors, 404s, 403s and exceptions render as JSON even when the
 * client forgot the header. Put it on API controllers.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Json implements RouteModifier
{
    public function modify(RouteDefinition $route): void
    {
        $route->middleware(ForceJson::class);
    }
}
