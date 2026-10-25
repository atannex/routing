<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Attribute;

/**
 * Declares a route for every HTTP method.
 *
 *     #[Any('/path/{id}', name: 'things.show')]
 *
 * See Route for the options.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Any extends Route
{
    /**
     * @param  string|list<string>  $middleware
     * @param  string|list<string>  $withoutMiddleware
     * @param  array<string, string>  $where
     * @param  array<string, mixed>  $defaults
     */
    public function __construct(
        string $uri = '/',
        ?string $name = null,
        string|array $middleware = [],
        string|array $withoutMiddleware = [],
        array $where = [],
        array $defaults = [],
        ?string $domain = null,
        ?int $priority = null,
        ?bool $scopeBindings = null,
        ?bool $withTrashed = null,
    ) {
        parent::__construct(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $uri, $name, $middleware, $withoutMiddleware, $where, $defaults, $domain, $priority, $scopeBindings, $withTrashed);
    }
}
