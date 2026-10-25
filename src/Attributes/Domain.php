<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Restricts routes to a domain: #[Domain('{account}.example.com')], or one
 * read from config at registration: #[Domain(config: 'app.admin_domain')].
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Domain implements RouteModifier
{
    public function __construct(
        public ?string $domain = null,
        public ?string $config = null,
    ) {}

    public function modify(RouteDefinition $route): void
    {
        if ($this->config !== null) {
            $route->domain('@config:'.$this->config);

            return;
        }

        $route->domain($this->domain);
    }
}
