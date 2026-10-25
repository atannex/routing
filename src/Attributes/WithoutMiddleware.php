<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Removes middleware the route would otherwise get from its group:
 * #[WithoutMiddleware(SomeMiddleware::class)].
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class WithoutMiddleware implements RouteModifier
{
    /** @var list<string> */
    public array $middleware;

    /**
     * @param  string|list<string>  $middleware
     */
    public function __construct(string|array $middleware, string ...$more)
    {
        $this->middleware = [...(array) $middleware, ...array_values($more)];
    }

    public function modify(RouteDefinition $route): void
    {
        $route->withoutMiddleware($this->middleware);
    }
}
