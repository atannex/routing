<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Default parameter values: #[Defaults(['page' => 'home'])] or
 * #[Defaults('page', 'home')]. They reach the controller like parameters.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Defaults implements RouteModifier
{
    /** @var array<string, mixed> */
    public array $defaults;

    /**
     * @param  string|array<string, mixed>  $key
     */
    public function __construct(string|array $key, mixed $value = null)
    {
        $this->defaults = is_array($key) ? $key : [$key => $value];
    }

    public function modify(RouteDefinition $route): void
    {
        $route->defaults($this->defaults);
    }
}
