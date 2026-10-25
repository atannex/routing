<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Scheduled;

use Atannex\Routing\Attributes\Schedule;

/** Commands of other packages, scheduled by name. */
#[Schedule('dailyAt:03:15', command: 'vendor:recount', parameters: ['--force' => true], withoutOverlapping: true)]
#[Schedule('weeklyOn:{fixtures.digest_day},{fixtures.digest_at=06:30}', command: 'vendor:digest')]
#[Schedule('everyMinute', command: 'vendor:poll', whenConfig: 'fixtures.poll_channel')]
final class VendorTasks {}
