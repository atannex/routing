<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Attribute;

/**
 * A resource without the create and edit form routes (JSON APIs).
 * Takes the same options as Resource.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class ApiResource extends Resource
{
    /**
     * @param  list<string>|null  $only
     * @param  list<string>  $except
     * @param  string|array<string, string>|null  $names
     * @param  array<string, string>  $parameters
     */
    public function __construct(
        string $name,
        ?array $only = null,
        array $except = [],
        string|array|null $names = null,
        array $parameters = [],
        bool $shallow = false,
        ?string $authorize = null,
        array $scoped = [],
    ) {
        parent::__construct($name, $only, $except, $names, $parameters, $shallow, api: true, authorize: $authorize, scoped: $scoped);
    }
}
