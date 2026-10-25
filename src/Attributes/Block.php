<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Session blocking (Route::block()): only one request per session runs the
 * route at a time; others wait up to $waitSeconds for the lock.
 *
 *     #[Post('checkout'), Block(lockSeconds: 10, waitSeconds: 10)]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Block implements RouteModifier
{
    public function __construct(
        public ?int $lockSeconds = 10,
        public ?int $waitSeconds = 10,
    ) {}

    public function modify(RouteDefinition $route): void
    {
        $route->block = [$this->lockSeconds, $this->waitSeconds];
    }
}
