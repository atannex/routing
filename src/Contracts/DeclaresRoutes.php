<?php

declare(strict_types=1);

namespace Atannex\Routing\Contracts;

use Atannex\Routing\Definition\RouteDefinition;
use ReflectionClass;
use ReflectionMethod;

/**
 * An attribute that declares routes (Get, Post, Resource, Fallback...).
 *
 * $method is the method carrying the attribute, or null when it sits on the
 * class (then the attribute decides which methods it routes to).
 */
interface DeclaresRoutes
{
    /**
     * @param  ReflectionClass<object>  $class
     * @return list<RouteDefinition>
     */
    public function routes(ReflectionClass $class, ?ReflectionMethod $method): array;
}
