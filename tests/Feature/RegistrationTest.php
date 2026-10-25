<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Feature;

use Atannex\Routing\Tests\Fixtures\Controllers\Admin\UsersController;
use Atannex\Routing\Tests\Fixtures\Controllers\SportsController;
use Atannex\Routing\Tests\TestCase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

final class RegistrationTest extends TestCase
{
    public function test_invokable_controller_on_the_class(): void
    {
        $route = $this->route('about');

        $this->assertSame('about', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame('Atannex\Routing\Tests\Fixtures\Controllers\AboutController', $route->getActionName());
        $this->get('/about')->assertOk()->assertSee('about');
    }

    public function test_groups_nest_prefix_names_and_middleware_from_the_outside_in(): void
    {
        $home = $this->route('sports.home');
        $this->assertSame('sports', $home->uri());
        $this->assertSame(['web', 'fixture.outer'], $home->middleware());

        $match = $this->route('sports.matches.show');
        $this->assertSame('sports/matches/{match}', $match->uri());
        $this->assertSame(['web', 'fixture.outer', 'fixture.inner'], $match->middleware());
        $this->assertSame('[0-9]+', $match->wheres['match']);
        $this->assertSame(SportsController::class.'@match', $match->getActionName());

        $predict = $this->route('sports.predict');
        $this->assertSame(['POST'], $predict->methods());
        $this->assertSame(['web', 'fixture.outer', 'throttle:30,1', 'fixture.route'], $predict->middleware());
    }

    public function test_repeated_attributes_give_one_action_several_addresses_with_their_own_defaults(): void
    {
        $this->assertSame(['page' => 'home'], $this->route('sports.home')->defaults);
        $this->assertSame(['page' => 'news'], $this->route('sports.news')->defaults);

        $this->get('/sports')->assertSee('sports:home');
        $this->get('/sports/news')->assertSee('sports:news');
        $this->get('/sports/matches/12')->assertSee('match:12');
        $this->post('/sports/predict/12')->assertSee('predicted:12');
    }

    public function test_methods_without_attributes_are_not_routed(): void
    {
        foreach ($this->app['router']->getRoutes() as $route) {
            $this->assertStringNotContainsString('@helper', $route->getActionName());
        }
    }

    public function test_abstract_parents_share_groups_and_routed_methods(): void
    {
        $users = $this->route('admin.users');
        $this->assertSame('admin/people/users', $users->uri());
        $this->assertSame(['web', 'fixture.admin', 'fixture.staff'], $users->middleware());
        $this->assertSame(['staff' => true], $users->getAction('routing')['meta']);

        $ping = $this->route('admin.ping');
        $this->assertSame('admin/people/ping', $ping->uri());
        $this->assertSame(UsersController::class.'@ping', $ping->getActionName());
        $this->get('/admin/people/ping')->assertSee(UsersController::class);
    }

    public function test_no_routes_hides_a_class(): void
    {
        $this->assertNull($this->app['router']->getRoutes()->getByName('hidden'));
    }

    public function test_resources_route_only_the_methods_the_controller_has(): void
    {
        $this->assertSame('photos', $this->route('photos.index')->uri());
        $this->assertSame('photos/{photo}', $this->route('photos.show')->uri());
        $this->assertSame(['POST'], $this->route('photos.store')->methods());
        $this->assertSame(['DELETE'], $this->route('photos.destroy')->methods());
        $this->assertContains('fixture.inner', $this->route('photos.store')->middleware());
        $this->assertNull($this->app['router']->getRoutes()->getByName('photos.create'));
        $this->assertNull($this->app['router']->getRoutes()->getByName('photos.edit'));

        $this->get('/photos/3')->assertSee('photo:3');
        $this->delete('/photos/3')->assertSee('destroyed:3');
    }

    public function test_singletons_and_nested_api_resources(): void
    {
        $this->assertSame('profile', $this->route('profile.show')->uri());
        $this->assertSame('profile/edit', $this->route('profile.edit')->uri());
        $this->assertSame(['PUT', 'PATCH'], $this->route('profile.update')->methods());
        $this->assertNull($this->app['router']->getRoutes()->getByName('profile.destroy'));

        $this->assertSame('users/{user}/comments', $this->route('users.comments.index')->uri());
        $this->assertSame('users/{user}/comments/{comment}', $this->route('users.comments.show')->uri());
        $this->assertNull($this->app['router']->getRoutes()->getByName('users.comments.update'), 'excluded by only');
    }

    public function test_domain_and_csrf_exemption(): void
    {
        $this->assertSame('api.example.test', $this->route('api.status')->getDomain());
        $this->assertSame([ValidateCsrfToken::class], $this->route('hooks')->excludedMiddleware());
        $this->get('http://api.example.test/status')->assertSee('api');
    }

    public function test_the_route_action_records_where_it_was_declared(): void
    {
        $routing = $this->route('about')->getAction('routing');

        $this->assertStringEndsWith('Fixtures/Controllers/AboutController.php:10', $routing['source']);
        $this->assertSame('Atannex\Routing\Tests\Fixtures\Controllers\AboutController::__invoke', $routing['handler']);
    }
}
