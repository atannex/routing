<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers;

use Atannex\Routing\Attributes\Get;

#[Get('/about', name: 'about')]
final class AboutController
{
    public function __invoke(): string
    {
        return 'about';
    }
}
