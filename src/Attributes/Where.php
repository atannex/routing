<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Constrains a parameter with a regex, or with a named pattern from
 * config/routing.php (patterns) when it starts with "@":
 *
 *     #[Where('year', '\d{4}')]
 *     #[Where('path', '@slug-path')]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Where implements RouteModifier
{
    public function __construct(
        public string $parameter,
        public string $pattern,
    ) {}

    public function modify(RouteDefinition $route): void
    {
        foreach (array_map('trim', explode(',', $this->parameter)) as $parameter) {
            $route->where($parameter, $this->pattern);
        }
    }
}
