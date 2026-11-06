<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures;

final class AuthorResolver
{
    public function resolve(string $value): string
    {
        return 'author:'.strtoupper($value);
    }
}
