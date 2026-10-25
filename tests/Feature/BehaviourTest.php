<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Feature;

use Atannex\Routing\Facades\Routing;
use Atannex\Routing\Tests\Fixtures\Elsewhere\HealthController;
use Atannex\Routing\Tests\TestCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;

final class BehaviourTest extends TestCase
{
    public function test_environment_and_config_conditions(): void
    {
        $this->assertNull($this->app['router']->getRoutes()->getByName('dev.tools'));
        $this->assertNull($this->app['router']->getRoutes()->getByName('beta'));

        $this->app['env'] = 'local';
        $this->app['config']->set('fixtures.beta', true);
        Routing::flush();
        Routing::register();

        $this->assertNotNull($this->app['router']->getRoutes()->getByName('dev.tools'));
        $this->assertNotNull($this->app['router']->getRoutes()->getByName('beta'));
    }

    public function test_input_validation_passes_the_validated_data_on(): void
    {
        $this->getJson('/search?q=abc&extra=1')->assertOk()->assertExactJson(['q' => 'abc', 'all' => ['q' => 'abc']]);
    }

    public function test_input_validation_fails_like_laravel_does(): void
    {
        $this->getJson('/search')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson('/search?q=toolong')->assertUnprocessable()->assertJsonPath('errors.q.0', 'Too long.');
        $this->from('/about')->get('/search?q=toolong')->assertRedirect('/about')->assertSessionHasErrors('q');
    }

    public function test_parameter_validation_returns_404(): void
    {
        $this->get('/archive/2024')->assertSee('archive:2024');
        $this->get('/archive/1800')->assertNotFound();
        $this->get('/archive/abc')->assertNotFound();
    }

    public function test_session_blocking_and_precognition(): void
    {
        $route = $this->app['router']->getRoutes()->getByName('checkout');

        $this->assertSame(5, $route->locksFor());
        $this->assertSame(3, $route->waitsFor());
        $this->assertContains(HandlePrecognitiveRequests::class, $route->middleware());
    }

    public function test_deprecation_headers(): void
    {
        $this->get('/legacy')
            ->assertOk()
            ->assertHeader('Deprecation', '@'.strtotime('2026-01-01 00:00:00 UTC'))
            ->assertHeader('Sunset', 'Thu, 01 Jan 2099 00:00:00 GMT')
            ->assertHeader('Link', '<http://localhost/search>; rel="successor-version"');

        $this->get('/gone')->assertStatus(410);
    }

    public function test_missing_models_redirect(): void
    {
        Route::bind('club', fn () => throw new ModelNotFoundException);

        $this->get('/clubs/ghost')->assertRedirect('/about');
    }

    public function test_classes_and_directories_added_at_runtime(): void
    {
        $this->routing = ['register' => false];
        $this->refreshApplication();

        Routing::controller(HealthController::class, ['prefix' => 'ops', 'as' => 'ops.']);
        Routing::register();

        $this->assertSame('ops/health', $this->route('ops.health')->uri());
        $this->get('/ops/health')->assertSee('ok');
    }

    public function test_the_deepest_directory_owns_its_classes(): void
    {
        $this->routing = ['directories' => [
            __DIR__.'/../Fixtures/Controllers' => ['middleware' => ['web']],
            __DIR__.'/../Fixtures/Controllers/Admin' => ['middleware' => ['web'], 'prefix' => 'back-office'],
        ]];
        $this->refreshApplication();

        $this->assertSame('back-office/admin/people/users', $this->route('admin.users')->uri());
        $this->assertSame('about', $this->route('about')->uri());
    }

    public function test_an_api_directory_gets_the_api_group_and_prefix(): void
    {
        $this->routing = ['directories' => [
            __DIR__.'/../Fixtures/Controllers' => ['middleware' => ['web']],
            __DIR__.'/../Fixtures/Api' => ['prefix' => 'api', 'as' => 'api.', 'middleware' => ['api']],
        ]];
        $this->refreshApplication();

        $this->assertSame('api/posts', $this->route('api.posts.index')->uri());
        $this->assertSame('api/posts/{post}', $this->route('api.posts.show')->uri());
        $this->assertSame(['api'], $this->route('api.posts.like')->gatherMiddleware());
        $this->assertNull($this->app['router']->getRoutes()->getByName('api.posts.create'));

        $this->getJson('/api/posts/7')->assertOk()->assertExactJson(['post' => '7']);
        // The api group has no CSRF check, so a token-less POST goes through.
        $this->postJson('/api/posts/7/like')->assertOk()->assertExactJson(['liked' => '7']);
    }

    public function test_automatic_names_from_the_uri(): void
    {
        $this->routing = ['naming' => 'uri'];
        $this->refreshApplication();

        // /hooks is named in the fixture; the unnamed fallback stays unnamed.
        $this->assertNotNull($this->app['router']->getRoutes()->getByName('hooks'));
        $this->assertSame([], array_filter(
            Routing::routes(),
            fn ($route) => $route->kind === 'route' && $route->fullName() === null,
        ));
    }

    public function test_manual_registration(): void
    {
        $this->routing = ['register' => false];
        $this->refreshApplication();

        $this->assertNull($this->app['router']->getRoutes()->getByName('about'));

        Routing::register();
        $this->assertNotNull($this->app['router']->getRoutes()->getByName('about'));
    }

    public function test_the_discovery_cache_round_trips(): void
    {
        $live = array_map(fn ($route) => $route->toArray(), Routing::discover());

        $this->assertSame(count($live), Routing::cache());
        $this->assertTrue(Routing::isCached());
        $this->assertSame($live, array_map(fn ($route) => $route->toArray(), Routing::definitions()));

        Routing::clearCache();
        $this->assertFalse(Routing::isCached());
    }
}
