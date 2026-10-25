<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers;

use Atannex\Routing\Attributes\Get;
use Atannex\Routing\Attributes\WhereIn;

#[Get('/{desk}', name: 'desk')]
#[WhereIn('desk', config: 'fixtures.desks', keys: true)]
final class DeskController
{
    public function __invoke(string $desk): string
    {
        return 'desk:'.$desk;
    }
}
