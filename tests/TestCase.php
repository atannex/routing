<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests;

use Atannex\Routing\RoutingServiceProvider;
use Atannex\Routing\Tests\Fixtures\PassThrough;
use Illuminate\Routing\Route;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** @var array<string, mixed> routing config overrides for the next app */
    protected array $routing = [];

    protected function getPackageProviders($app): array
    {
        return [RoutingServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('routing.directories', [
            __DIR__.'/Fixtures/Controllers' => ['middleware' => ['web']],
        ]);
        $app['config']->set('routing.cache', sys_get_temp_dir().'/atannex-routing-test-'.getmypid().'.php');
        $app['config']->set('fixtures.desks', ['news' => [], 'politics' => []]);
        $app['config']->set('fixtures.beta', false);

        foreach ($this->routing as $key => $value) {
            $app['config']->set("routing.{$key}", $value);
        }

        foreach (['fixture.outer', 'fixture.inner', 'fixture.route', 'fixture.admin', 'fixture.staff'] as $alias) {
            $app['router']->aliasMiddleware($alias, PassThrough::class);
        }
    }

    protected function tearDown(): void
    {
        @unlink(sys_get_temp_dir().'/atannex-routing-test-'.getmypid().'.php');

        parent::tearDown();
    }

    protected function route(string $name): Route
    {
        $route = $this->app['router']->getRoutes()->getByName($name);
        $this->assertNotNull($route, "Route [{$name}] is not registered.");

        return $route;
    }

    /** @return list<string> URIs in registration order */
    protected function order(): array
    {
        return array_map(fn (Route $route) => $route->uri(), $this->app['router']->getRoutes()->getRoutes());
    }
}
