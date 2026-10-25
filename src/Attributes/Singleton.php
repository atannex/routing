<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\DeclaresRoutes;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

/**
 * A singleton resource: one thing, no id in the URL (a profile, settings).
 *
 *     #[Singleton('profile')]                       // show, edit, update
 *     #[Singleton('users.avatar', creatable: true)] // + create, store, destroy
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Singleton implements DeclaresRoutes
{
    /**
     * @param  list<string>|null  $only
     * @param  list<string>  $except
     * @param  array<string, string>  $parameters
     */
    public function __construct(
        public string $name,
        public bool $creatable = false,
        public bool $destroyable = false,
        public bool $api = false,
        public ?array $only = null,
        public array $except = [],
        public ?string $names = null,
        public array $parameters = [],
    ) {}

    public function routes(ReflectionClass $class, ?ReflectionMethod $method): array
    {
        $segments = explode('.', $this->name);
        $last = array_pop($segments);

        $path = '';
        foreach ($segments as $segment) {
            $path .= $segment.'/{'.($this->parameters[$segment] ?? str_replace('-', '_', Str::singular($segment))).'}/';
        }
        $path .= $last;

        $actions = [
            'create' => [['GET'], $path.'/create', $this->creatable && ! $this->api],
            'store' => [['POST'], $path, $this->creatable],
            'show' => [['GET'], $path, true],
            'edit' => [['GET'], $path.'/edit', ! $this->api],
            'update' => [['PUT', 'PATCH'], $path, true],
            'destroy' => [['DELETE'], $path, $this->creatable || $this->destroyable],
        ];

        $routes = [];
        foreach ($actions as $action => [$methods, $uri, $enabled]) {
            if (! $enabled || ! $class->hasMethod($action)
                || ($this->only !== null && ! in_array($action, $this->only, true))
                || in_array($action, $this->except, true)) {
                continue;
            }

            $route = new RouteDefinition($methods, $uri, $class->getName(), $action);
            $route->name(($this->names ?? $this->name).'.'.$action);
            $routes[] = $route;
        }

        return $routes;
    }
}
