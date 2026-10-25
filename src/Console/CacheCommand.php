<?php

declare(strict_types=1);

namespace Atannex\Routing\Console;

use Atannex\Routing\Routing;
use Illuminate\Console\Command;

final class CacheCommand extends Command
{
    protected $signature = 'routing:cache';

    protected $description = 'Cache the discovered attribute routes so requests skip reflection';

    public function handle(Routing $routing): int
    {
        $count = $routing->cache();

        $this->components->info("Attribute routes cached ({$count} routes).");

        return self::SUCCESS;
    }
}
