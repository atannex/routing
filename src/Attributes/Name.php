<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Prefixes the name of every named route below it, like
 * Route::name('sports.')->group(): #[Name('sports.')]. Prefixes nest, and
 * unnamed routes stay unnamed. (Name a single route with `name:` on its
 * Get/Post... attribute.)
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Name implements RouteModifier
{
    public function __construct(public string $name) {}

    public function modify(RouteDefinition $route): void
    {
        $route->as($this->name);
    }
}
