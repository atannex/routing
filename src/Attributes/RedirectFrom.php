<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Keeps an old address working by redirecting it to this route (301 by
 * default). Parameters with the same name carry over:
 *
 *     #[Get('/', name: 'landing')]
 *     #[RedirectFrom('/welcome')]
 *
 *     #[Get('/stories/{slug}')]
 *     #[RedirectFrom('/posts/{slug}', status: 302)]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class RedirectFrom implements RouteModifier
{
    public function __construct(
        public string $uri,
        public int $status = 301,
    ) {}

    public function modify(RouteDefinition $route): void
    {
        $route->redirectsFrom[] = [$this->uri, $this->status];
    }
}
