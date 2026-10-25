<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/** Requires a verified email address: #[Verified], or #[Verified('verification.notice')]. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Verified implements RouteModifier
{
    public function __construct(public ?string $redirectToRoute = null) {}

    public function modify(RouteDefinition $route): void
    {
        $route->middleware($this->redirectToRoute === null ? 'verified' : 'verified:'.$this->redirectToRoute);
    }
}
