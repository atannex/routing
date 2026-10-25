<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Attribute;

/**
 * Declares a PUT route.
 *
 *     #[Put('/path/{id}', name: 'things.show')]
 *
 * See Route for the options.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Put extends Route
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
        parent::__construct(['PUT'], $uri, $name, $middleware, $withoutMiddleware, $where, $defaults, $domain, $priority, $scopeBindings, $withTrashed);
    }
}
