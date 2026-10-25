<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Feature;

use Atannex\Routing\Facades\Routing;
use Atannex\Routing\Tests\Fixtures\PackageRoutesProvider;
use Atannex\Routing\Tests\TestCase;

final class OrderingAndConstraintsTest extends TestCase
{
    public function test_specific_routes_win_over_catch_alls_whatever_the_file_order(): void
    {
        $order = $this->order();

        $this->assertLessThan(array_search('{desk}', $order), array_search('about', $order));
        $this->assertLessThan(array_search('{path}', $order), array_search('{desk}', $order));
        $this->assertLessThan(array_search('{fallbackPlaceholder}', $order), array_search('{path}', $order), 'the fallback is last');

        $this->get('/about')->assertSee('about');
        $this->get('/news')->assertSee('desk:news');
        $this->get('/business/farming')->assertSee('content:business/farming');
    }

    protected function getPackageProviders($app): array
    {
        // Booted after this package, like any package listed later.
        return [...parent::getPackageProviders($app), PackageRoutesProvider::class];
    }

    public function test_by_default_attribute_routes_come_after_package_routes(): void
    {
        // The app's catch-all must not swallow a package's routes.
        $order = $this->order();

        $this->assertContains('vendor-package/{path}', $order);
        $this->assertLessThan(array_search('{path}', $order), array_search('vendor-package/{path}', $order));
        $this->get('/vendor-package/a/b')->assertSee('package:a/b');
    }

    public function test_boot_mode_registers_ahead_of_later_routes(): void
    {
        $this->routing = ['register' => 'boot'];
        $this->refreshApplication();

        $order = $this->order();
        $this->assertGreaterThan(array_search('{path}', $order), array_search('vendor-package/{path}', $order));
    }

    public function test_priority_beats_specificity(): void
    {
        $order = $this->order();

        // {anything} is more specific than the slash-spanning {path}, but has priority -10.
        $this->assertGreaterThan(array_search('{path}', $order), array_search('{anything}', $order));
        $this->get('/whatever')->assertSee('content:whatever');
    }

    public function test_domain_routes_come_first(): void
    {
        $this->assertSame('api.example.test', Routing::routes()[0]->domain);
    }

    public function test_value_lists_from_config_enums_callables_and_literals(): void
    {
        $this->assertSame('news|politics', $this->route('desk')->wheres['desk']);
        $this->assertSame('draft|published', $this->route('status')->wheres['status']);
        $this->assertSame('red|green', $this->route('colour')->wheres['colour']);
        $this->assertSame('s|m|x\.l', $this->route('size')->wheres['size']);

        $this->get('/status/draft')->assertSee('draft');
        $this->get('/status/archived')->assertSee('content:status/archived', false);
        $this->get('/size/x.l')->assertSee('x.l');
        $this->get('/size/x!l')->assertNotFound();
    }

    public function test_named_patterns(): void
    {
        $this->assertSame('[A-Za-z0-9\-]+(?:/[A-Za-z0-9\-]+)*', $this->route('content')->wheres['path']);
    }

    public function test_the_fallback_runs_the_middleware_and_returns_404(): void
    {
        $fallback = collect($this->app['router']->getRoutes()->getRoutes())->firstWhere('isFallback', true);

        $this->assertNotNull($fallback);
        $this->assertSame(['web'], $fallback->middleware());
        $this->get('/no/such!page')->assertNotFound()->assertSee('fixture fallback');
    }

    public function test_old_addresses_redirect_to_the_route_and_carry_parameters(): void
    {
        $this->get('/welcome')->assertStatus(301)->assertRedirect('/');
        $this->get('/old-stories/hello-world')->assertStatus(302)->assertRedirect('/stories/hello-world');
        $this->get('/old-stories/Bad-Slug')->assertSee('content:old-stories/Bad-Slug');
    }

    public function test_redirects_keep_only_the_directory_middleware(): void
    {
        $redirect = collect($this->app['router']->getRoutes()->getRoutes())->first(fn ($route) => $route->uri() === 'welcome');

        $this->assertSame(['web'], $redirect->middleware());
        $this->assertSame(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $redirect->methods());
    }

    public function test_project_wide_parameter_constraints(): void
    {
        $this->routing = ['parameters' => ['team' => '@id', 'match' => '[a-z]+']];
        $this->refreshApplication();

        $this->assertSame('[0-9]+', $this->route('teams.show')->wheres['team']);
        $this->assertSame('[0-9]+', $this->route('sports.matches.show')->wheres['match'], 'an explicit constraint wins');
    }
}
