<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Authorizes with a gate or policy before the controller runs:
 *
 *     #[Can('view', 'advert')]          // policy on the bound {advert}
 *     #[Can('create', Post::class)]
 *     #[Can('access-newsroom')]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Can implements RouteModifier
{
    /** @var list<string> */
    public array $models;

    public function __construct(public string $ability, string ...$models)
    {
        $this->models = array_values($models);
    }

    public function modify(RouteDefinition $route): void
    {
        $route->middleware('can:'.implode(',', [$this->ability, ...$this->models]));
    }
}
