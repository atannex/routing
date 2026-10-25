<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/** Asks for the password again before continuing (password.confirm). */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class PasswordConfirm implements RouteModifier
{
    public function __construct(public ?string $redirectToRoute = null, public ?int $timeout = null) {}

    public function modify(RouteDefinition $route): void
    {
        $arguments = array_filter([$this->redirectToRoute, $this->timeout], fn ($value) => $value !== null);

        $route->middleware($arguments === [] ? 'password.confirm' : 'password.confirm:'.implode(',', $arguments));
    }
}
