<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Scopes nested implicit bindings (/users/{user}/posts/{post} only finds the
 * user's posts). #[ScopeBindings(false)] turns it off below a scoped group.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class ScopeBindings implements RouteModifier
{
    public function __construct(public bool $scope = true) {}

    public function modify(RouteDefinition $route): void
    {
        $route->scopeBindings($this->scope);
    }
}
