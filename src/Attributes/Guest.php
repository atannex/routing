<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Only for visitors who are not signed in: #[Guest], or for given guards: #[Guest('web', 'api')].
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Guest implements RouteModifier
{
    /** @var list<string> */
    public array $guards;

    public function __construct(string ...$guards)
    {
        $this->guards = array_values($guards);
    }

    public function modify(RouteDefinition $route): void
    {
        $route->middleware($this->guards === [] ? 'guest' : 'guest:'.implode(',', $this->guards));
    }
}
