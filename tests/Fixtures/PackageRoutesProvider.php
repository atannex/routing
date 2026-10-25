<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;

/** Stands in for another package (Fortify, Filament...) that registers routes while it boots. */
final class PackageRoutesProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['router']->get('vendor-package/{path}', fn (string $path) => 'package:'.$path)
            ->where('path', '.*');
    }
}
