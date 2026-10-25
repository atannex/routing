<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Registers routes only when a config value matches (a feature switch):
 *
 *     #[WhenConfig('features.sports')]               // truthy
 *     #[WhenConfig('services.mail.driver', 'postmark')]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class WhenConfig implements RouteModifier
{
    public function __construct(public string $key, public mixed $value = true) {}

    public function modify(RouteDefinition $route): void
    {
        $route->condition(['config', $this->key, $this->value]);
    }
}
