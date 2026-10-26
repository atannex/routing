<?php

declare(strict_types=1);

namespace Atannex\Routing\Registration;

use Atannex\Routing\Definition\RouteDefinition;
use Atannex\Routing\Exceptions\RoutingException;
use Atannex\Routing\Http\Middleware\DeprecatedRoute;
use Atannex\Routing\Http\Middleware\ValidateRouteRequest;
use Atannex\Routing\Http\Middleware\VerifyWebhookSignature;
use Atannex\Routing\RoutingServiceProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\RedirectController;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * Prepares definitions (conditions, redirects, constraints, names, order)
 * and hands them to Laravel's router.
 */
final class RouteRegistrar
{
    /** Every verb, as Router::any() and Route::redirect() use. */
    private const ANY = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * @param  array<string, mixed>  $config  config/routing.php
     */
    public function __construct(
        private readonly Container $container,
        private readonly ConstraintResolver $constraints,
        private readonly array $config = [],
    ) {}

    /**
     * The routes as they will be registered: conditions applied, redirects
     * expanded, constraints resolved to regexes, names generated, sorted.
     *
     * @param  list<RouteDefinition>  $definitions
     * @return list<RouteDefinition>
     */
    public function prepare(array $definitions): array
    {
        $prepared = [];

        foreach ($definitions as $definition) {
            if (! $this->passes($definition)) {
                continue;
            }

            $route = clone $definition;
            $this->resolveConstraints($route);
            $this->resolveDomain($route);
            $this->generateName($route);
            $prepared[] = $route;

            foreach ($route->redirectsFrom as [$uri, $status]) {
                $prepared[] = $this->redirectTo($route, $uri, $status);
            }
        }

        if (($this->config['ordering'] ?? 'specificity') === 'specificity') {
            $prepared = Specificity::sort($prepared);
        } else {
            // "declaration": discovery order, with priorities and fallbacks still honoured.
            usort($prepared, fn (RouteDefinition $a, RouteDefinition $b) => ($a->kind === RouteDefinition::KIND_FALLBACK) <=> ($b->kind === RouteDefinition::KIND_FALLBACK)
                ?: $b->priority <=> $a->priority
                ?: $a->sequence <=> $b->sequence);
        }

        return $prepared;
    }

    /**
     * Registers prepared definitions with the router.
     *
     * @param  list<RouteDefinition>  $prepared
     * @return list<Route>
     */
    public function register(Router $router, array $prepared): array
    {
        return array_map(fn (RouteDefinition $definition) => $this->add($router, $definition), $prepared);
    }

    private function add(Router $router, RouteDefinition $definition): Route
    {
        $route = match ($definition->kind) {
            RouteDefinition::KIND_FALLBACK => $router->fallback($definition->uses()),
            RouteDefinition::KIND_REDIRECT => $router->any($definition->uri(), '\\'.RedirectController::class)
                ->defaults('destination', $definition->redirect[0])
                ->defaults('status', $definition->redirect[1]),
            default => $router->match($definition->methods, $definition->uri(), $definition->uses()),
        };

        if ($definition->domain !== null) {
            $route->domain($definition->domain);
        }

        if (($name = $definition->fullName()) !== null) {
            $route->name($name);
        }

        $middleware = $definition->middleware;
        if ($definition->deprecated !== null) {
            $middleware[] = DeprecatedRoute::class;
        }
        if ($definition->throttle !== null) {
            $middleware[] = 'throttle:'.RoutingServiceProvider::RATE_LIMITER;
        }
        if ($definition->webhook !== null) {
            $middleware[] = VerifyWebhookSignature::class;
        }
        if ($definition->validate !== null || $definition->parameterRules !== []) {
            $middleware[] = ValidateRouteRequest::class;
        }

        if ($middleware !== []) {
            $route->middleware($middleware);
        }

        if ($definition->withoutMiddleware !== []) {
            $route->withoutMiddleware($definition->withoutMiddleware);
        }

        foreach ($definition->wheres as $parameter => $pattern) {
            $route->where($parameter, $pattern);
        }

        foreach ($definition->defaults as $key => $value) {
            $route->defaults($key, $value);
        }

        if ($definition->scopeBindings === true) {
            $route->scopeBindings();
        } elseif ($definition->scopeBindings === false) {
            $route->withoutScopedBindings();
        }

        if ($definition->withTrashed) {
            $route->withTrashed();
        }

        if ($definition->bindingFields !== []) {
            $fields = array_intersect_key($definition->bindingFields, array_flip($definition->parameterNames()));
            $route->setBindingFields(array_merge($route->bindingFields(), $fields));
        }

        if ($definition->block !== null) {
            $route->block(...$definition->block);
        }

        if ($definition->missing !== null) {
            $route->missing($this->missingHandler($definition->missing));
        }

        // Written straight onto the action: setAction(array_merge(...)) would
        // copy the whole action array of every route.
        $route->action['routing'] = array_filter([
            'source' => $definition->source,
            'handler' => $definition->handler(),
            'priority' => $definition->priority ?: null,
            'validate' => $definition->validate,
            'parameters' => $definition->parameterRules ?: null,
            'parameter_status' => $definition->parameterRules ? $definition->parameterStatus : null,
            'deprecated' => $definition->deprecated,
            'throttle' => $definition->throttle,
            'webhook' => $definition->webhook,
            'meta' => $definition->meta ?: null,
        ], fn ($value) => $value !== null);

        return $route;
    }

    /* ------------------------------------------------------------------ */

    private function passes(RouteDefinition $definition): bool
    {
        foreach ($definition->conditions as $condition) {
            $passes = match ($condition[0]) {
                'env' => $this->container->make('app')->environment(...$condition[1]),
                'config' => $condition[2] === true
                    ? (bool) $this->container->make('config')->get($condition[1])
                    : $this->container->make('config')->get($condition[1]) == $condition[2],
                'using' => (bool) $this->container->call($condition[1]),
                default => throw new RoutingException("Unknown route condition \"{$condition[0]}\"."),
            };

            if (! $passes) {
                return false;
            }
        }

        return true;
    }

    private function resolveConstraints(RouteDefinition $route): void
    {
        $wheres = [];

        foreach ($route->wheres as $parameter => $spec) {
            $wheres[$parameter] = $this->constraints->resolve($spec);
        }

        // Project-wide constraints by parameter name (routing.parameters).
        foreach ((array) ($this->config['parameters'] ?? []) as $parameter => $spec) {
            if (! isset($wheres[$parameter]) && in_array($parameter, $route->parameterNames(), true)) {
                $wheres[$parameter] = $this->constraints->resolve($spec);
            }
        }

        if ($route->kind === RouteDefinition::KIND_FALLBACK) {
            $wheres['fallbackPlaceholder'] = '.*';
        }

        $route->wheres = $wheres;
    }

    private function resolveDomain(RouteDefinition $route): void
    {
        if ($route->domain !== null && str_starts_with($route->domain, '@config:')) {
            $domain = $this->container->make('config')->get(substr($route->domain, 8));
            $route->domain = is_string($domain) && $domain !== '' ? $domain : null;
        }
    }

    private function generateName(RouteDefinition $route): void
    {
        $strategy = $this->config['naming'] ?? null;

        if ($strategy === null || $route->name !== null || $route->kind !== RouteDefinition::KIND_ROUTE) {
            return;
        }

        if (is_array($strategy) || (is_string($strategy) && str_contains($strategy, '@'))) {
            $name = $this->container->call($strategy, ['route' => $route]);
        } elseif ($strategy === 'uri') {
            $name = self::nameFromUri($route);
        } else {
            throw new RoutingException("Unknown naming strategy \"{$strategy}\". Use null, \"uri\" or a [class, method] callable.");
        }

        if (is_string($name) && $name !== '') {
            $route->name = $name;
        }
    }

    /** "sports/leagues/{league}/table" (GET) => "sports.leagues.table"; POST adds ".store". */
    public static function nameFromUri(RouteDefinition $route): string
    {
        $segments = array_filter(
            explode('/', trim($route->path, '/')),
            fn (string $segment) => $segment !== '' && ! str_contains($segment, '{'),
        );

        $name = implode('.', array_map(fn (string $segment) => Str::slug($segment), $segments)) ?: 'index';

        $verb = match (true) {
            in_array('GET', $route->methods, true) => null,
            in_array('POST', $route->methods, true) => 'store',
            in_array('PUT', $route->methods, true), in_array('PATCH', $route->methods, true) => 'update',
            in_array('DELETE', $route->methods, true) => 'destroy',
            default => strtolower($route->methods[0] ?? 'any'),
        };

        return $verb === null ? $name : $name.'.'.$verb;
    }

    private function redirectTo(RouteDefinition $target, string $uri, int $status): RouteDefinition
    {
        $redirect = new RouteDefinition(self::ANY, $uri, RedirectController::class, '__invoke', RouteDefinition::KIND_REDIRECT);
        $redirect->redirect = ['/'.ltrim($target->uri(), '/'), $status];
        $redirect->domain = $target->domain;
        $redirect->priority = $target->priority;
        $redirect->middleware = $target->baseMiddleware;
        $redirect->source = $target->source;
        $redirect->sequence = $target->sequence;
        $redirect->wheres = array_intersect_key($target->wheres, array_flip($redirect->parameterNames()));

        return $redirect;
    }

    /**
     * @param  array<string, mixed>  $missing
     */
    private function missingHandler(array $missing): \Closure
    {
        if (isset($missing['redirect'])) {
            $name = (string) $missing['redirect'];

            return static fn () => redirect()->route($name);
        }

        $handler = $missing['handler'];

        return static fn ($request, $exception = null) => app()->call($handler, [
            'request' => $request,
            'exception' => $exception,
        ]);
    }
}
