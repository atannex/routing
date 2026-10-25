<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Attribute;

/**
 * Constrains one or more parameters to digits only: #[WhereNumber('id')], #[WhereNumber('a, b')].
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class WhereNumber extends Where
{
    public function __construct(string $parameter)
    {
        parent::__construct($parameter, '[0-9]+');
    }
}
