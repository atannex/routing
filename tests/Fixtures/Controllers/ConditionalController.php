<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers;

use Atannex\Routing\Attributes\Environment;
use Atannex\Routing\Attributes\Get;
use Atannex\Routing\Attributes\WhenConfig;

final class ConditionalController
{
    #[Get('/dev/tools', name: 'dev.tools')]
    #[Environment('local')]
    public function tools(): string
    {
        return 'tools';
    }

    #[Get('/beta', name: 'beta')]
    #[WhenConfig('fixtures.beta')]
    public function beta(): string
    {
        return 'beta';
    }
}
