<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers\Admin;

use Atannex\Routing\Attributes\Get;
use Atannex\Routing\Attributes\NoRoutes;

#[NoRoutes]
#[Get('/hidden', name: 'hidden')]
final class HiddenController
{
    public function __invoke(): string
    {
        return 'hidden';
    }
}
