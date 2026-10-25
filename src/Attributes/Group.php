<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Everything a Route::group() takes, in one attribute.
 *
 *     #[Group(prefix: 'account', as: 'account.', middleware: ['auth', 'verified'])]
 *
 * Put it on a base controller to share it with every controller that
 * extends it.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Group implements RouteModifier
{
    /**
     * @param  string|list<string>  $middleware
     * @param  string|list<string>  $withoutMiddleware
     * @param  array<string, string>  $where
     * @param  array<string, mixed>  $defaults
     */
    public function __construct(
        public ?string $prefix = null,
        public ?string $as = null,
        public string|array $middleware = [],
        public string|array $withoutMiddleware = [],
        public ?string $domain = null,
        public array $where = [],
        public array $defaults = [],
        public ?bool $scopeBindings = null,
        public ?int $priority = null,
    ) {}

    public function modify(RouteDefinition $route): void
    {
        $route->prefix((string) $this->prefix)
            ->as((string) $this->as)
            ->middleware($this->middleware)
            ->withoutMiddleware($this->withoutMiddleware)
            ->wheres($this->where)
            ->defaults($this->defaults);

        if ($this->domain !== null) {
            $route->domain($this->domain);
        }

        if ($this->scopeBindings !== null) {
            $route->scopeBindings($this->scopeBindings);
        }

        if ($this->priority !== null) {
            $route->priority($this->priority);
        }
    }
}
