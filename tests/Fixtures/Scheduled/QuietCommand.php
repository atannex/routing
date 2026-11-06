<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Scheduled;

use Illuminate\Console\Command;

final class QuietCommand extends Command
{
    protected $signature = 'fixture:quiet';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
