<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/** Lets implicit bindings find soft-deleted models. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class WithTrashed implements RouteModifier
{
    public function __construct(public bool $withTrashed = true) {}

    public function modify(RouteDefinition $route): void
    {
        $route->withTrashed($this->withTrashed);
    }
}
