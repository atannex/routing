<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Feature;

use Atannex\Routing\Facades\Routing;
use Atannex\Routing\Tests\Fixtures\Controllers\AboutController;
use Atannex\Routing\Tests\Fixtures\Elsewhere\BrokenController;
use Atannex\Routing\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

final class CommandsTest extends TestCase
{
    public function test_list_shows_the_routes_in_order_with_their_source(): void
    {
        $this->assertSame(0, Artisan::call('routing:list', ['--path' => 'sports']));
        $output = Artisan::output();
        $this->assertStringContainsString('sports/matches/{match}', $output);
        $this->assertStringContainsString('SportsController@match', $output);
        $this->assertStringContainsString('SportsController.php:', $output);
        $this->assertStringNotContainsString('photos', $output);

        Artisan::call('routing:list', ['--json' => true]);
        $rows = json_decode(Artisan::output(), true);

        $this->assertSame('status', $rows[0]['uri'] === 'api.example.test status' ? 'status' : $rows[0]['uri']);
        $this->assertSame('fallback', end($rows)['kind']);
    }

    public function test_cache_and_clear(): void
    {
        $this->artisan('routing:cache')->expectsOutputToContain('Attribute routes cached')->assertSuccessful();
        $this->assertTrue(Routing::isCached());

        $this->artisan('routing:clear')->assertSuccessful();
        $this->assertFalse(Routing::isCached());
    }

    public function test_lint_reports_shadowed_routes_as_warnings(): void
    {
        $this->artisan('routing:lint')
            ->expectsOutputToContain('unreachable')
            ->assertSuccessful();

        $this->artisan('routing:lint', ['--strict' => true])->assertFailed();
    }

    public function test_lint_catches_broken_declarations(): void
    {
        $this->routing = ['register' => false];
        $this->refreshApplication();
        Routing::controller(BrokenController::class);
        Routing::register();

        $this->assertSame(1, Artisan::call('routing:lint'));
        $output = Artisan::output();

        $this->assertStringContainsString('GET /about is declared twice', $output);
        $this->assertStringContainsString('Unknown middleware "no-such-middleware"', $output);
        $this->assertStringContainsString('cannot fill {thing}', $output);
        $this->assertStringContainsString('The name "about" is used by', $output);
        $this->assertStringNotContainsString('Unknown middleware "web"', $output);
    }

    public function test_convert_prints_attributes_for_route_file_routes(): void
    {
        Route::middleware(['web', 'auth'])->group(function () {
            Route::get('/legacy-about/{id}', AboutController::class)->whereNumber('id')->name('legacy.about')->defaults('tab', 'x');
            Route::permanentRedirect('/older', '/legacy-about/1');
            Route::get('/closure', fn () => 'x');
        });

        $this->assertSame(0, Artisan::call('routing:convert'));
        $output = Artisan::output();

        $this->assertStringContainsString(AboutController::class, $output);
        $this->assertStringContainsString("#[Get('/legacy-about/{id}', name: 'legacy.about', middleware: 'auth', where: ['id' => '[0-9]+'], defaults: ['tab' => 'x'])]", $output);
        $this->assertStringContainsString("#[RedirectFrom('/older')]  // to /legacy-about/1", $output);
        $this->assertStringContainsString('GET|HEAD /closure', $output);
        $this->assertStringNotContainsString('sports/matches', $output, 'attribute routes are left out');
    }
}
