<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Prefixes the URI of every route below it: #[Prefix('sports')].
 * Prefixes nest: directory, parent class, class, method.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Prefix implements RouteModifier
{
    public function __construct(public string $prefix) {}

    public function modify(RouteDefinition $route): void
    {
        $route->prefix($this->prefix);
    }
}
