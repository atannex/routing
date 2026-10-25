<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;

/**
 * Binds a parameter by another column, without writing {post:slug} in
 * every URI: #[Bind('post', 'slug')], or several: #[Bind(['post' => 'slug', 'author' => 'username'])].
 * Parameters a route does not have are ignored.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Bind implements RouteModifier
{
    /** @var array<string, string> */
    public array $fields;

    /**
     * @param  string|array<string, string>  $parameter
     */
    public function __construct(string|array $parameter, ?string $field = null)
    {
        $this->fields = is_array($parameter) ? $parameter : [$parameter => (string) $field];
    }

    public function modify(RouteDefinition $route): void
    {
        foreach ($this->fields as $parameter => $field) {
            $route->bind($parameter, $field);
        }
    }
}
