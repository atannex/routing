<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Rate limiting:
 *
 *     #[Throttle(30, 1)]                         // 30 a minute (Laravel's throttle:30,1)
 *     #[Throttle('uploads')]                     // a limiter defined with RateLimiter::for()
 *     #[Throttle(5, 1, by: 'input:email')]       // 5 a minute per e-mail address, no RateLimiter::for() needed
 *     #[Throttle(100, 60, by: 'user')]           // 100 an hour per user (per IP for guests)
 *
 * `by` keys the limit: ip, user, session, header:Name, input:field or
 * route:parameter. Each route keeps its own counter. A request without the
 * header, field or parameter is counted by IP instead.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Throttle implements RouteModifier
{
    public function __construct(
        public int|string $limit,
        public ?int $minutes = null,
        public ?string $prefix = null,
        public ?string $by = null,
    ) {
        if ($by === null) {
            return;
        }

        if (! is_int($limit)) {
            throw new \InvalidArgumentException("`by` needs a number limit, not the named limiter \"{$limit}\".");
        }

        if (! preg_match('/^(ip|user|session|(header|input|route):.+)$/', $by)) {
            throw new \InvalidArgumentException("Unknown throttle key \"{$by}\". Use ip, user, session, header:Name, input:field or route:parameter.");
        }
    }

    public function modify(RouteDefinition $route): void
    {
        if ($this->by !== null && is_int($this->limit)) {
            $route->throttle = ['limit' => $this->limit, 'minutes' => $this->minutes ?? 1, 'by' => $this->by];

            return;
        }

        if (is_string($this->limit)) {
            $route->middleware('throttle:'.$this->limit);

            return;
        }

        $arguments = array_filter([$this->limit, $this->minutes ?? 1, $this->prefix], fn ($value) => $value !== null);

        $route->middleware('throttle:'.implode(',', $arguments));
    }
}
