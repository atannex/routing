<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Atannex\Routing\Exceptions\RoutingException;
use Attribute;

/**
 * Restricts a parameter to a list of values. The list can be written out,
 * or read when the routes are registered, so it never goes stale:
 *
 *     #[WhereIn('platform', ['facebook', 'x'])]
 *     #[WhereIn('platform', ShareService::PLATFORMS)]            // a constant
 *     #[WhereIn('edition', config: 'editions.editions', keys: true)]
 *     #[WhereIn('status', enum: Status::class)]                 // backed enum values
 *     #[WhereIn('newsletter', using: [Newsletter::class, 'all'])]
 *
 * `enum` also takes a class with a static getValues() (bensampo/laravel-enum).
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class WhereIn implements RouteModifier
{
    /**
     * @param  list<string|int>|null  $values
     * @param  class-string|null  $enum
     * @param  array{0: class-string, 1: string}|null  $using
     */
    public function __construct(
        public string $parameter,
        public ?array $values = null,
        public ?string $config = null,
        public bool $keys = false,
        public ?string $enum = null,
        public ?array $using = null,
    ) {
        if ($values === null && $config === null && $enum === null && $using === null) {
            throw new RoutingException("#[WhereIn('{$parameter}')] needs values, config, enum or using.");
        }
    }

    public function modify(RouteDefinition $route): void
    {
        $spec = match (true) {
            $this->values !== null => ['in' => array_values($this->values)],
            $this->config !== null => ['config' => $this->config, 'keys' => $this->keys],
            $this->enum !== null => ['enum' => $this->enum],
            default => ['using' => $this->using, 'keys' => $this->keys],
        };

        foreach (array_map('trim', explode(',', $this->parameter)) as $parameter) {
            $route->where($parameter, $spec);
        }
    }
}
