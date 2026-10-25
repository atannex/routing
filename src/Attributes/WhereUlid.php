<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Attribute;

/**
 * Constrains one or more parameters to a ULID: #[WhereUlid('id')], #[WhereUlid('a, b')].
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class WhereUlid extends Where
{
    public function __construct(string $parameter)
    {
        parent::__construct($parameter, '[0-7][0-9a-hjkmnp-tv-zA-HJKMNP-TV-Z]{25}');
    }
}
