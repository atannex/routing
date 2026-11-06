<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/** A project shorthand built on the extension point. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Staff implements RouteModifier
{
    public function modify(RouteDefinition $route): void
    {
        $route->middleware('fixture.staff')->meta('staff', true);
    }
}
