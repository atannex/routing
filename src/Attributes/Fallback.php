<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\DeclaresRoutes;
use Atannex\Routing\Definition\RouteDefinition;
use Atannex\Routing\Exceptions\RoutingException;
use Attribute;
use ReflectionClass;
use ReflectionMethod;

/**
 * The route for every URL nothing else matched. Unlike the router's own 404,
 * it runs the route middleware (sessions, the signed-in user), so the error
 * page knows who is reading:
 *
 *     #[Fallback]
 *     final class NotFoundController { public function __invoke() { abort(404); } }
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Fallback implements DeclaresRoutes
{
    public function routes(ReflectionClass $class, ?ReflectionMethod $method): array
    {
        if ($method === null && ! $class->hasMethod('__invoke')) {
            throw new RoutingException("#[Fallback] on {$class->getName()} needs an __invoke method.");
        }

        return [new RouteDefinition(
            ['GET', 'HEAD'],
            '{fallbackPlaceholder}',
            $class->getName(),
            $method?->getName() ?? '__invoke',
            RouteDefinition::KIND_FALLBACK,
        )];
    }
}
