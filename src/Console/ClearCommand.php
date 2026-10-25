<?php

declare(strict_types=1);

namespace Atannex\Routing\Console;

use Atannex\Routing\Routing;
use Illuminate\Console\Command;

final class ClearCommand extends Command
{
    protected $signature = 'routing:clear';

    protected $description = 'Remove the attribute route discovery cache';

    public function handle(Routing $routing): int
    {
        $routing->clearCache();

        $this->components->info('Attribute route cache cleared.');

        return self::SUCCESS;
    }
}
