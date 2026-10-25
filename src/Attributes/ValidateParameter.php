<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Validates a route parameter with Laravel's rules, for what a regex cannot
 * say. A parameter that fails is a 404 by default (the address does not
 * exist), checked on the raw value before model binding:
 *
 *     #[ValidateParameter('year', 'integer|between:1990,2100')]
 *     #[ValidateParameter('date', 'date_format:Y-m-d|before_or_equal:today', status: 400)]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidateParameter implements RouteModifier
{
    /**
     * @param  string|list<mixed>  $rules
     */
    public function __construct(
        public string $parameter,
        public string|array $rules,
        public int $status = 404,
    ) {}

    public function modify(RouteDefinition $route): void
    {
        $route->parameterRules[$this->parameter] = $this->rules;
        $route->parameterStatus = $this->status;
    }
}
