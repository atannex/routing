<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Marks routes as deprecated. Responses carry the standard headers
 * (Deprecation, Sunset, Link rel="successor-version"), and once the sunset
 * date has passed the route answers 410 Gone if `gone` is set:
 *
 *     #[Deprecated(since: '2026-10-01', sunset: '2027-01-01', successor: 'api.v2.posts')]
 *
 * `successor` is a route name or a URL.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Deprecated implements RouteModifier
{
    public function __construct(
        public ?string $since = null,
        public ?string $sunset = null,
        public ?string $successor = null,
        public bool $gone = false,
    ) {}

    public function modify(RouteDefinition $route): void
    {
        $route->deprecated = [
            'since' => $this->since,
            'sunset' => $this->sunset,
            'successor' => $this->successor,
            'gone' => $this->gone,
        ];
    }
}
