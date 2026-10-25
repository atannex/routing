<?php

declare(strict_types=1);

namespace Atannex\Routing\Console;

use Atannex\Routing\Definition\RouteDefinition;
use Atannex\Routing\Routing;
use Atannex\Routing\Support\SampleUri;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Throwable;

/**
 * Checks the routes for mistakes a route file would only show at runtime:
 * duplicate names and addresses, routes another route swallows, unknown
 * middleware, redirects that lose parameters, missing handlers.
 */
final class LintCommand extends Command
{
    protected $signature = 'routing:lint {--strict : Treat warnings as errors}';

    protected $description = 'Check the routes for duplicates, shadowed routes and broken declarations';

    /** @var list<array{0: string, 1: string}> */
    private array $problems = [];

    public function handle(Routing $routing, Router $router): int
    {
        // The HTTP kernel hands its middleware groups and aliases to the router
        // when it is built, which a console command never does on its own.
        if ($this->laravel->bound(HttpKernel::class)) {
            $this->laravel->make(HttpKernel::class);
        }

        try {
            $definitions = $routing->routes();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->duplicates($definitions);
        $this->declarations($definitions, $router);
        $this->names($definitions, $router);
        $this->sessionless($definitions);
        $this->shadows($router);

        $errors = count(array_filter($this->problems, fn (array $problem) => $problem[0] === 'error'));
        $warnings = count($this->problems) - $errors;

        foreach ($this->problems as [$level, $message]) {
            $level === 'error' ? $this->components->error($message) : $this->components->warn($message);
        }

        if ($this->problems === []) {
            $this->components->info('No problems in '.count($router->getRoutes()->getRoutes()).' routes ('.count($definitions).' from attributes).');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("  {$errors} error(s), {$warnings} warning(s).");

        return $errors > 0 || ($this->option('strict') && $warnings > 0) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<RouteDefinition>  $definitions
     */
    private function duplicates(array $definitions): void
    {
        $seen = [];

        foreach ($definitions as $definition) {
            foreach (array_diff($definition->methods, ['HEAD']) as $method) {
                $key = $method.' '.($definition->domain ?? '').'/'.$definition->uri();

                if (isset($seen[$key])) {
                    $this->problem('error', "{$key} is declared twice: {$seen[$key]} and {$definition->source}. Only the last one would work.");
                }

                $seen[$key] = $definition->source;
            }
        }
    }

    /**
     * @param  list<RouteDefinition>  $definitions
     */
    private function declarations(array $definitions, Router $router): void
    {
        $aliases = $router->getMiddleware() + $router->getMiddlewareGroups();

        foreach ($definitions as $definition) {
            foreach ([...$definition->middleware, ...$definition->withoutMiddleware] as $middleware) {
                $name = explode(':', $middleware, 2)[0];

                if (! isset($aliases[$name]) && ! class_exists($name)) {
                    $this->problem('error', "Unknown middleware \"{$name}\" on {$definition->handler()} ({$definition->source}).");
                }
            }

            if ($definition->kind === RouteDefinition::KIND_REDIRECT) {
                $lost = array_diff(
                    $this->parameters($definition->redirect[0]),
                    $definition->parameterNames(),
                );

                if ($lost !== []) {
                    $this->problem('error', "The redirect from /{$definition->uri()} cannot fill {".implode('}, {', $lost)."} of {$definition->redirect[0]} ({$definition->source}).");
                }

                continue;
            }

            if (! method_exists($definition->controller, $definition->action)) {
                $this->problem('error', "{$definition->handler()} does not exist ({$definition->source}).");
            }

            if (is_string($definition->validate['rules'] ?? null) && ! class_exists($definition->validate['rules'])) {
                $this->problem('error', "Validation class {$definition->validate['rules']} does not exist ({$definition->source}).");
            }

            if (isset($definition->missing['handler']) && ! is_callable($definition->missing['handler'])) {
                $this->problem('error', 'Missing-model handler '.implode('::', $definition->missing['handler'])." is not a public static method ({$definition->source}).");
            }

            $unknown = array_diff(array_keys($definition->parameterRules), $definition->parameterNames());
            if ($unknown !== []) {
                $this->problem('warning', '#[ValidateParameter] for {'.implode('}, {', $unknown)."}, which {$definition->uri()} does not have ({$definition->source}).");
            }

            $unused = array_diff(array_keys($definition->wheres), [...$definition->parameterNames(), 'fallbackPlaceholder']);
            if ($unused !== []) {
                $this->problem('warning', 'Constraint for {'.implode('}, {', $unused)."}, which {$definition->uri()} does not have ({$definition->source}).");
            }
        }
    }

    /**
     * Names are checked on the declarations as well as the router, because
     * the router silently replaces a route registered twice at one address.
     *
     * @param  list<RouteDefinition>  $definitions
     */
    private function names(array $definitions, Router $router): void
    {
        $names = [];
        $candidates = array_map(fn (RouteDefinition $definition) => [$definition->fullName(), $definition->uri()], $definitions);

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if ($route->getAction('routing') === null) {
                $candidates[] = [$route->getName(), $route->uri()];
            }
        }

        foreach ($candidates as [$name, $uri]) {
            if ($name === null) {
                continue;
            }

            if (isset($names[$name]) && $names[$name] !== $uri) {
                $this->problem('error', "The name \"{$name}\" is used by /{$names[$name]} and /{$uri}; route('{$name}') only reaches one.");
            }

            $names[$name] = $uri;
        }
    }

    private function shadows(Router $router): void
    {
        $routes = array_values(array_filter($router->getRoutes()->getRoutes(), fn (Route $route) => ! $route->isFallback));

        foreach ($routes as $index => $route) {
            $sample = SampleUri::for($route);
            $host = $route->getDomain();

            if ($sample === null || ($host !== null && str_contains($host, '{'))) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $request = Request::create('http://'.($host ?? 'localhost').'/'.ltrim($sample, '/'), $method);

                if (! $route->matches($request)) {
                    continue 2;
                }

                for ($i = 0; $i < $index; $i++) {
                    if (in_array($method, $routes[$i]->methods(), true) && $routes[$i]->matches($request)) {
                        $this->problem('warning', sprintf(
                            '%s /%s (%s) is unreachable for /%s: %s /%s (%s) matches it first.',
                            $method, $route->uri(), $this->label($route), $sample, $method, $routes[$i]->uri(), $this->label($routes[$i]),
                        ));

                        continue 3;
                    }
                }
            }
        }
    }

    private function label(Route $route): string
    {
        return $route->getAction('routing')['source'] ?? $route->getName() ?? $route->getActionName();
    }

    /**
     * @return list<string>
     */
    /**
     * #[Stateless] and #[Webhook] drop the session, which session-guard auth,
     * guest checks, password confirmation and redirect-back validation need.
     *
     * @param  list<RouteDefinition>  $definitions
     */
    private function sessionless(array $definitions): void
    {
        $config = $this->laravel['config'];

        foreach ($definitions as $definition) {
            if (! in_array(StartSession::class, $definition->withoutMiddleware, true)) {
                continue;
            }

            foreach ($definition->middleware as $middleware) {
                [$name, $arguments] = array_pad(explode(':', $middleware, 2), 2, null);

                if (in_array($name, ['auth', 'guest'], true)) {
                    $guards = $arguments === null ? [$config->get('auth.defaults.guard')] : explode(',', $arguments);
                    $session = array_filter($guards, fn ($guard) => $config->get("auth.guards.{$guard}.driver") === 'session');

                    if ($session !== []) {
                        $this->problem('warning', "{$definition->handler()} has {$middleware} with the session guard \"".implode(', ', $session)."\", but no session: nobody can be signed in ({$definition->source}).");
                    }
                } elseif ($name === 'password.confirm') {
                    $this->problem('warning', "{$definition->handler()} confirms the password but has no session to remember it ({$definition->source}).");
                }
            }
        }
    }

    private function parameters(string $uri): array
    {
        preg_match_all('/\{(\w+)(?::\w+)?\??\}/', $uri, $matches);

        return $matches[1];
    }

    private function problem(string $level, string $message): void
    {
        $this->problems[] = [$level, $message];
    }
}
