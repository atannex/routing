<?php

declare(strict_types=1);

namespace Atannex\Routing;

use Atannex\Routing\Broadcasting\ChannelRegistrar;
use Atannex\Routing\Console\CacheCommand;
use Atannex\Routing\Console\ClearCommand;
use Atannex\Routing\Console\CommandFinder;
use Atannex\Routing\Console\ConvertCommand;
use Atannex\Routing\Console\LintCommand;
use Atannex\Routing\Console\ListCommand;
use Atannex\Routing\Http\Middleware\ValidateRouteRequest;
use Atannex\Routing\Scheduling\ScheduleRegistrar;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RoutingServiceProvider extends ServiceProvider
{
    /** The limiter behind #[Throttle(..., by: ...)]; it reads each route's own limit. */
    public const RATE_LIMITER = 'atannex.routing';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/routing.php', 'routing');

        $this->app->singleton(Routing::class, fn (Application $app) => new Routing($app, $app['files']));
        $this->app->alias(Routing::class, 'atannex.routing');

        $this->app->singleton(ChannelRegistrar::class, fn (Application $app) => new ChannelRegistrar(
            $app->make(Routing::class)->channelDirectories(),
            $app->make(Routing::class)->cached('channels'),
        ));

        $this->app->singleton(ScheduleRegistrar::class, fn (Application $app) => new ScheduleRegistrar(
            $app->make(Routing::class)->scheduleDirectories(),
            $app->make(Routing::class)->cached('schedule'),
        ));
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/routing.php' => config_path('routing.php')], 'routing-config');

        if ($this->app->runningInConsole()) {
            $this->commands([ListCommand::class, CacheCommand::class, ClearCommand::class, LintCommand::class, ConvertCommand::class]);

            if (method_exists($this, 'optimizes')) {
                $this->optimizes(optimize: 'routing:cache', clear: 'routing:clear', key: 'routing');
            }

            AboutCommand::add('Routing', fn () => [
                'Registration' => var_export($this->app['config']->get('routing.register'), true),
                'Discovery cache' => $this->app->make(Routing::class)->isCached() ? '<fg=green;options=bold>CACHED</>' : '<fg=yellow;options=bold>NOT CACHED</>',
                'Directories' => (string) count($this->app->make(Routing::class)->directories()),
            ]);
        }

        if ($this->app['config']->get('routing.schedule.enabled', true)) {
            $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $this->app->make(ScheduleRegistrar::class)->register($schedule));
        }

        if ($this->app->runningInConsole() && ($directories = $this->app->make(Routing::class)->commandDirectories()) !== []) {
            Artisan::starting(fn (Artisan $artisan) => $artisan->resolveCommands((new CommandFinder)->find($directories)));
        }

        if (class_exists(BroadcastManager::class) && $this->app['config']->get('routing.channels.enabled', true)) {
            $this->callAfterResolving(BroadcastManager::class, fn (BroadcastManager $broadcast) => $this->app->make(ChannelRegistrar::class)->register($broadcast));
        }

        $this->bootRateLimiter();
        $this->bootBindings();

        Request::macro('routeValidated', function (?string $key = null, mixed $default = null) {
            /** @var Request $this */
            return data_get($this->attributes->get(ValidateRouteRequest::VALIDATED, []), $key, $default);
        });

        $mode = $this->app['config']->get('routing.register', 'booted');

        if ($mode === false || $this->app->routesAreCached()) {
            return;
        }

        $register = fn () => $this->app->make(Routing::class)->register($this->app->make('router'));

        $mode === 'boot' ? $register() : $this->app->booted($register);
    }

    /**
     * One limiter serves every #[Throttle(..., by: ...)] route: it reads the
     * limit from the route, so nothing is defined per route and it works
     * with the route cache.
     */
    private function bootRateLimiter(): void
    {
        RateLimiter::for(self::RATE_LIMITER, function (Request $request) {
            $route = $request->route();
            $spec = (array) ($route?->getAction('routing')['throttle'] ?? []);

            if ($spec === []) {
                return Limit::none();
            }

            $by = (string) $spec['by'];
            [$source, $field] = array_pad(explode(':', $by, 2), 2, null);

            $key = match ($source) {
                'ip' => $request->ip(),
                'user' => $request->user()?->getAuthIdentifier() ?? $request->ip(),
                'session' => $request->hasSession() ? $request->session()->getId() : $request->ip(),
                'header' => $request->header((string) $field),
                'input' => $request->input((string) $field),
                'route' => $route?->originalParameter((string) $field),
                default => throw new Exceptions\RoutingException("Unknown throttle key \"{$by}\". Use ip, user, session, header:Name, input:field or route:parameter."),
            };

            // Without the header, field or parameter, count by IP, so leaving
            // the field out does not put every such request in one bucket.
            if ($key === null || $key === '' || $key === []) {
                [$source, $key] = ['ip', $request->ip()];
            }

            return Limit::perMinutes((int) $spec['minutes'], (int) $spec['limit'])
                ->by(($route?->getName() ?? $route?->uri()).'|'.$source.'|'.(is_scalar($key) ? (string) $key : sha1((string) json_encode($key))));
        });
    }

    /**
     * Global bindings from config: a model class (Route::model), or a
     * [class, method] / "Class@method" resolver receiving the value and the route (Route::bind).
     */
    private function bootBindings(): void
    {
        foreach ((array) $this->app['config']->get('routing.bindings', []) as $parameter => $binder) {
            $router = $this->app->make('router');

            if (is_string($binder) && class_exists($binder) && ! method_exists($binder, '__invoke')) {
                $router->model($parameter, $binder);

                continue;
            }

            // [Class::class, 'method'] is resolved from the container, not called statically.
            if (is_array($binder) && is_string($binder[0] ?? null) && ! (new \ReflectionMethod($binder[0], $binder[1]))->isStatic()) {
                $binder = $binder[0].'@'.$binder[1];
            }

            $router->bind($parameter, fn ($value, $route) => $this->app->call($binder, ['value' => $value, 'route' => $route]));
        }
    }
}
