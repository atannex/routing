<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * What to do when an implicitly bound model is not found, instead of a 404:
 *
 *     #[Missing(redirect: 'sports.teams')]          // to a named route
 *     #[Missing(handler: 'teamNotFound')]           // a static method of the controller
 *
 * The handler receives the request and the exception and returns a response.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Missing implements RouteModifier
{
    public function __construct(
        public ?string $redirect = null,
        public ?string $handler = null,
    ) {}

    public function modify(RouteDefinition $route): void
    {
        $route->missing = $this->redirect !== null
            ? ['redirect' => $this->redirect]
            : ['handler' => [$route->controller, $this->handler ?? 'missing']];
    }
}
