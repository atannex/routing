<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Elsewhere;

use Atannex\Routing\Attributes\Get;

#[Get('/health', name: 'health')]
final class HealthController
{
    public function __invoke(): string
    {
        return 'ok';
    }
}
