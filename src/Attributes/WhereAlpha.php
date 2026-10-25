<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Attribute;

/**
 * Constrains one or more parameters to letters only: #[WhereAlpha('id')], #[WhereAlpha('a, b')].
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class WhereAlpha extends Where
{
    public function __construct(string $parameter)
    {
        parent::__construct($parameter, '[a-zA-Z]+');
    }
}
