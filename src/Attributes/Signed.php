<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Requires a valid signature (URL::signedRoute / temporarySignedRoute).
 * #[Signed(relative: true)] accepts relative signed URLs.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Signed implements RouteModifier
{
    public function __construct(public bool $relative = false) {}

    public function modify(RouteDefinition $route): void
    {
        $route->middleware($this->relative ? 'signed:relative' : 'signed');
    }
}
