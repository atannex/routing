<?php

declare(strict_types=1);

namespace Atannex\Routing\Contracts;

use Atannex\Routing\Definition\RouteDefinition;

/**
 * An attribute that changes the routes it applies to.
 *
 * On a class it applies to every route of the class (and of its subclasses),
 * on a method to every route of that method. Implement it on your own
 * attributes to add project shorthands:
 *
 *     #[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
 *     final class Staff implements RouteModifier
 *     {
 *         public function modify(RouteDefinition $route): void
 *         {
 *             $route->middleware(['auth', 'can:access-newsroom']);
 *         }
 *     }
 */
interface RouteModifier
{
    public function modify(RouteDefinition $route): void;
}
