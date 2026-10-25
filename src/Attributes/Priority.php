<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Moves routes ahead of (positive) or behind (negative) the others.
 *
 * Routes are normally ordered by specificity (static paths before
 * parameters, constrained parameters before open ones, catch-alls last),
 * so this is rarely needed. Priority always wins over specificity.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Priority implements RouteModifier
{
    public function __construct(public int $priority) {}

    public function modify(RouteDefinition $route): void
    {
        $route->priority($this->priority);
    }
}
