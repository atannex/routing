<?php

declare(strict_types=1);

namespace Atannex\Routing;

use Atannex\Routing\Attributes\Group;
use Atannex\Routing\Broadcasting\ChannelRegistrar;
use Atannex\Routing\Console\CommandFinder;
use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Atannex\Routing\Discovery\ClassFinder;
use Atannex\Routing\Discovery\RouteReader;
use Atannex\Routing\Registration\ConstraintResolver;
use Atannex\Routing\Registration\RouteRegistrar;
use Atannex\Routing\Scheduling\ScheduleRegistrar;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\RedirectController;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Routing\ViewController;

/**
 * Finds the routes declared on controllers and registers them.
 *
 *     Routing::directory(app_path('Modules/Billing/Http'), ['prefix' => 'billing', 'middleware' => ['web']]);
 *     Routing::controller(HealthController::class);
 *     Routing::routes();          // the prepared definitions, in registration order
 */
class Routing
{
    /** @var array<string, array<string, mixed>> directories added at runtime */
    private array $directories = [];

    /** @var array<class-string, array<string, mixed>> classes added at runtime */
    private array $classes = [];

    /** @var list<RouteDefinition>|null */
    private ?array $definitions = null;

    private bool $registered = false;

    /** @var array<string, mixed>|null the discovery cache file, once read */
    private ?array $cacheData = null;

    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
    ) {}

    /**
     * Scan another directory (call it from a service provider's register()).
     *
     * @param  array<string, mixed>  $options  prefix, as, middleware, withoutMiddleware, domain, where, defaults, patterns, exclude
     */
    public function directory(string $path, array $options = []): static
    {
        $this->directories[$path] = $options;
        $this->definitions = null;

        return $this;
    }

    /**
     * Register a class outside the scanned directories.
     *
     * @param  class-string|list<class-string>  $classes
     * @param  array<string, mixed>  $options  same group options as a directory
     */
    public function controller(string|array $classes, array $options = []): static
    {
        foreach ((array) $classes as $class) {
            $this->classes[$class] = $options;
        }
        $this->definitions = null;

        return $this;
    }

    /**
     * The scanned directories with their options, keyed by absolute path.
     *
     * @return array<string, array<string, mixed>>
     */
    public function directories(): array
    {
        $directories = [];

        foreach ((array) $this->config('directories', []) as $path => $options) {
            if (is_int($path)) {
                [$path, $options] = [$options, []];
            }

            $directories[$this->absolute((string) $path)] = (array) $options;
        }

        foreach ($this->directories as $path => $options) {
            $directories[$this->absolute($path)] = $options;
        }

        return $directories;
    }

    /**
     * Where #[Schedule] classes are looked for, as absolute paths.
     *
     * @return list<string>
     */
    public function scheduleDirectories(): array
    {
        return $this->paths('schedule.directories');
    }

    /**
     * Where #[Channel] classes are looked for, as absolute paths.
     *
     * @return list<string>
     */
    public function channelDirectories(): array
    {
        return $this->paths('channels.directories');
    }

    /**
     * Extra directories whose console commands are registered, as absolute paths.
     *
     * @return list<string>
     */
    public function commandDirectories(): array
    {
        return $this->paths('commands');
    }

    /**
     * Every declared route, unconditioned and unsorted, read from the
     * discovery cache when there is one.
     *
     * @return list<RouteDefinition>
     */
    public function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        if (($cached = $this->cached('routes')) !== null) {
            return $this->definitions = array_map(RouteDefinition::fromArray(...), $cached);
        }

        return $this->definitions = $this->discover();
    }

    /**
     * Scan the directories and classes now, ignoring the cache.
     *
     * @return list<RouteDefinition>
     */
    public function discover(): array
    {
        $reader = new RouteReader($this->app->basePath());
        $finder = new ClassFinder;
        $sources = [];

        // When directories nest, the deepest one owns its classes (and their group options).
        $directories = $this->directories();
        uksort($directories, fn (string $a, string $b) => substr_count($b, '/') <=> substr_count($a, '/'));

        foreach ($directories as $path => $options) {
            foreach ($finder->find($path, (array) ($options['patterns'] ?? ['*.php']), (array) ($options['exclude'] ?? [])) as $class) {
                $sources[$class] ??= $options;
            }
        }

        foreach ($this->classes as $class => $options) {
            $sources[$class] = $options;
        }

        $definitions = [];
        $sequence = 0;

        foreach ($sources as $class => $options) {
            foreach ($reader->read($class, $this->groupModifiers($options)) as $definition) {
                $definition->sequence = $sequence++;
                $definitions[] = $definition;
            }
        }

        foreach ([...$this->configViews(), ...$this->configRedirects()] as $definition) {
            $definition->sequence = $sequence++;
            $definitions[] = $definition;
        }

        return $definitions;
    }

    /**
     * The routes as they will be (or were) registered, in order.
     *
     * @return list<RouteDefinition>
     */
    public function routes(): array
    {
        return $this->registrar()->prepare($this->definitions());
    }

    /**
     * Register the routes with the router (once).
     *
     * @return list<Route>
     */
    public function register(?Router $router = null): array
    {
        if ($this->registered) {
            return [];
        }

        $this->registered = true;
        $router ??= $this->app->make('router');

        $routes = $this->registrar()->register($router, $this->routes());

        // Names and actions are set after each route is added, so rebuild the
        // lookups (Laravel does this once at boot; routes added later need it).
        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();

        return $routes;
    }

    public function isRegistered(): bool
    {
        return $this->registered;
    }

    /* ------------------------------------------------------------------
     | Discovery cache
     * ------------------------------------------------------------------ */

    public function cachePath(): string
    {
        return (string) ($this->config('cache') ?: $this->app->bootstrapPath('cache/routing.php'));
    }

    public function isCached(): bool
    {
        return $this->files->exists($this->cachePath());
    }

    /**
     * One section of the discovery cache (routes, schedule, channels, commands), or
     * null when there is no cache or it predates that section.
     *
     * @return array<int|string, mixed>|null
     */
    public function cached(string $section): ?array
    {
        if ($this->cacheData === null) {
            if (! $this->isCached()) {
                return null;
            }

            $this->cacheData = (array) require $this->cachePath();
        }

        return isset($this->cacheData[$section]) ? (array) $this->cacheData[$section] : null;
    }

    /** Write the discovery cache; returns the number of routes. */
    public function cache(): int
    {
        $routes = array_map(fn (RouteDefinition $definition) => $definition->toArray(), $this->discover());

        $data = [
            'routes' => $routes,
            'schedule' => (new ScheduleRegistrar($this->scheduleDirectories()))->export(),
            'channels' => (new ChannelRegistrar($this->channelDirectories()))->export(),
            'commands' => (new CommandFinder)->find($this->commandDirectories()),
        ];

        // replace() writes a temporary file and renames it, so a request never
        // reads a half-written cache.
        $this->files->ensureDirectoryExists(dirname($this->cachePath()));
        $this->files->replace($this->cachePath(), '<?php return '.var_export($data, true).';'.PHP_EOL);
        $this->definitions = null;
        $this->cacheData = null;

        return count($routes);
    }

    public function clearCache(): bool
    {
        $this->definitions = null;
        $this->cacheData = null;

        return ! $this->isCached() || $this->files->delete($this->cachePath());
    }

    /** Forget what was discovered (tests, long-running workers). */
    public function flush(): void
    {
        $this->definitions = null;
        $this->cacheData = null;
        $this->registered = false;
    }

    /* ------------------------------------------------------------------ */

    public function constraints(): ConstraintResolver
    {
        return new ConstraintResolver($this->app, (array) $this->config('patterns', []));
    }

    public function registrar(): RouteRegistrar
    {
        return new RouteRegistrar($this->app, $this->constraints(), (array) $this->app['config']->get('routing', []));
    }

    /**
     * Pages that need no controller (routing.views):
     * 'terms' => 'pages.terms', or 'about' => ['view' => ..., 'name' => ..., 'data' => [...], 'middleware' => [...]].
     *
     * @return list<RouteDefinition>
     */
    private function configViews(): array
    {
        $definitions = [];

        foreach ((array) $this->config('views', []) as $uri => $spec) {
            $spec = is_string($spec) ? ['view' => $spec] : (array) $spec;

            $definition = new RouteDefinition(['GET', 'HEAD'], (string) $uri, ViewController::class);
            $definition->defaults([
                'view' => $spec['view'],
                'data' => (array) ($spec['data'] ?? []),
                'status' => (int) ($spec['status'] ?? 200),
                'headers' => (array) ($spec['headers'] ?? []),
            ]);

            $definitions[] = $this->configRoute($definition, $spec);
        }

        return $definitions;
    }

    /**
     * Plain redirects (routing.redirects): '/old' => '/new', or '/old' => ['to' => '/new', 'status' => 302].
     *
     * @return list<RouteDefinition>
     */
    private function configRedirects(): array
    {
        $definitions = [];

        foreach ((array) $this->config('redirects', []) as $uri => $spec) {
            $spec = is_string($spec) ? ['to' => $spec] : (array) $spec;

            $definition = new RouteDefinition(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], (string) $uri, RedirectController::class, '__invoke', RouteDefinition::KIND_REDIRECT);
            $definition->redirect = [(string) $spec['to'], (int) ($spec['status'] ?? 301)];

            $definitions[] = $this->configRoute($definition, $spec);
        }

        return $definitions;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function configRoute(RouteDefinition $definition, array $spec): RouteDefinition
    {
        $middleware = (array) ($spec['middleware'] ?? $this->config('static_middleware', ['web']));

        $definition->middleware($middleware);
        $definition->baseMiddleware = $definition->middleware;
        $definition->name($spec['name'] ?? null);
        $definition->domain($spec['domain'] ?? null);
        $definition->wheres((array) ($spec['where'] ?? []));
        $definition->source = 'config/routing.php';

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<RouteModifier>
     */
    private function groupModifiers(array $options): array
    {
        $group = array_intersect_key($options, array_flip(['prefix', 'as', 'middleware', 'withoutMiddleware', 'domain', 'where', 'defaults', 'scopeBindings', 'priority']));

        return $group === [] ? [] : [new Group(...$group)];
    }

    /** @return list<string> */
    private function paths(string $key): array
    {
        return array_values(array_map(fn ($path) => $this->absolute((string) $path), (array) $this->config($key, [])));
    }

    private function absolute(string $path): string
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? rtrim($path, '/')
            : rtrim($this->app->basePath($path), '/');
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return $this->app['config']->get('routing.'.$key, $default);
    }
}
