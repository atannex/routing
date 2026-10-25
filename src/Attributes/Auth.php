<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Requires a signed-in user: #[Auth], or for given guards: #[Auth('web', 'api')].
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Auth implements RouteModifier
{
    /** @var list<string> */
    public array $guards;

    public function __construct(string ...$guards)
    {
        $this->guards = array_values($guards);
    }

    public function modify(RouteDefinition $route): void
    {
        $route->middleware($this->guards === [] ? 'auth' : 'auth:'.implode(',', $this->guards));
    }
}
