<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;

/**
 * Answers Laravel Precognition requests (live validation from the front end)
 * by running the route's validation without the controller.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Precognitive implements RouteModifier
{
    public function modify(RouteDefinition $route): void
    {
        $route->middleware(HandlePrecognitiveRequests::class);
    }
}
