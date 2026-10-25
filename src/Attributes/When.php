<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Registers routes only when a static method returns true. It is called
 * through the container, so it can ask for dependencies:
 *
 *     #[When([Features::class, 'sportsEnabled'])]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class When implements RouteModifier
{
    /**
     * @param  array{0: class-string, 1: string}  $callback
     */
    public function __construct(public array $callback) {}

    public function modify(RouteDefinition $route): void
    {
        $route->condition(['using', $this->callback]);
    }
}
