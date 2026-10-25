<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Registers routes only in the given environments: #[Environment('local')],
 * #[Environment('local', 'staging')]. Elsewhere the URL is a plain 404.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Environment implements RouteModifier
{
    /** @var list<string> */
    public array $environments;

    public function __construct(string ...$environments)
    {
        $this->environments = array_values($environments);
    }

    public function modify(RouteDefinition $route): void
    {
        $route->condition(['env', $this->environments]);
    }
}
