<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Scheduled;

use Atannex\Routing\Attributes\Schedule;

final class Housekeeping
{
    public static array $ran = [];

    #[Schedule('weeklyOn:1,8:00')]
    public function pruneDrafts(): void
    {
        self::$ran[] = 'prune';
    }

    public function notScheduled(): void {}
}
