<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers\Admin;

use Atannex\Routing\Attributes\Get;
use Atannex\Routing\Attributes\Group;

#[Group(prefix: 'admin', as: 'admin.', middleware: ['fixture.admin'])]
abstract class AdminController
{
    #[Get('/ping', name: 'ping')]
    public function ping(): string
    {
        return static::class;
    }
}
