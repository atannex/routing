<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Scheduled;

use Atannex\Routing\Attributes\Schedule;

#[Schedule('everyFifteenMinutes', timezone: 'Africa/Douala')]
final class PruneTask
{
    public static int $runs = 0;

    public function __invoke(): void
    {
        self::$runs++;
    }
}
