<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Attribute;

/**
 * Constrains one or more parameters to a UUID: #[WhereUuid('id')], #[WhereUuid('a, b')].
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class WhereUuid extends Where
{
    public function __construct(string $parameter)
    {
        parent::__construct($parameter, '[\\da-fA-F]{8}-[\\da-fA-F]{4}-[\\da-fA-F]{4}-[\\da-fA-F]{4}-[\\da-fA-F]{12}');
    }
}
