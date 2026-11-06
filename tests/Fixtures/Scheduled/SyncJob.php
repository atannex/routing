<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Scheduled;

use Atannex\Routing\Attributes\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;

#[Schedule('hourly', queue: 'sync-feeds')]
final class SyncJob implements ShouldQueue
{
    public function handle(): void {}
}
