<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * HTTP cache headers on the response (Laravel's cache.headers middleware):
 *
 *     #[CacheHeaders('public;max_age=300;etag')]
 *     #[CacheHeaders(maxAge: 300, public: true, etag: true)]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class CacheHeaders implements RouteModifier
{
    public function __construct(
        public ?string $options = null,
        public ?int $maxAge = null,
        public ?int $sMaxAge = null,
        public bool $public = false,
        public bool $private = false,
        public bool $etag = false,
        public bool $noStore = false,
        public bool $mustRevalidate = false,
    ) {}

    public function modify(RouteDefinition $route): void
    {
        $options = $this->options ?? implode(';', array_filter([
            $this->public ? 'public' : null,
            $this->private ? 'private' : null,
            $this->noStore ? 'no_store' : null,
            $this->mustRevalidate ? 'must_revalidate' : null,
            $this->maxAge !== null ? 'max_age='.$this->maxAge : null,
            $this->sMaxAge !== null ? 's_maxage='.$this->sMaxAge : null,
            $this->etag ? 'etag' : null,
        ]));

        $route->middleware('cache.headers:'.$options);
    }
}
