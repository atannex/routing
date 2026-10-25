<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Scheduled;

use Atannex\Routing\Attributes\Schedule;
use Illuminate\Console\Command;

#[Schedule('dailyAt:06:00', parameters: ['--fresh' => true], withoutOverlapping: true, onOneServer: true)]
#[Schedule('0 18 * * 1-5', environments: ['production'], description: 'Evening report')]
final class ReportCommand extends Command
{
    protected $signature = 'fixture:report {--fresh}';

    protected $description = 'Builds the report';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
