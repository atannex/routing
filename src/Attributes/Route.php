<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\DeclaresRoutes;
use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Atannex\Routing\Exceptions\RoutingException;
use Attribute;
use ReflectionClass;
use ReflectionMethod;

/**
 * Declares a route for one or more HTTP methods.
 *
 *     #[Route(['GET', 'POST'], '/search', name: 'search')]
 *
 * On a method it routes to that method; on a class it routes to __invoke
 * (single-action controllers). Repeat it to give one action several
 * addresses. Each option here applies to this route only, and wins over
 * the method's and the class's attributes.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route implements DeclaresRoutes, RouteModifier
{
    /** @var list<string> */
    public array $methods;

    /**
     * @param  string|list<string>  $methods
     * @param  string|list<string>  $middleware
     * @param  string|list<string>  $withoutMiddleware
     * @param  array<string, string>  $where  regexes, or "@named-pattern"
     * @param  array<string, mixed>  $defaults
     */
    public function __construct(
        string|array $methods,
        public string $uri = '/',
        public ?string $name = null,
        public string|array $middleware = [],
        public string|array $withoutMiddleware = [],
        public array $where = [],
        public array $defaults = [],
        public ?string $domain = null,
        public ?int $priority = null,
        public ?bool $scopeBindings = null,
        public ?bool $withTrashed = null,
    ) {
        $this->methods = array_map('strtoupper', (array) $methods);
    }

    public function routes(ReflectionClass $class, ?ReflectionMethod $method): array
    {
        $action = $method?->getName() ?? '__invoke';

        if ($method === null && ! $class->hasMethod('__invoke')) {
            throw new RoutingException(sprintf(
                'The #[%s] attribute on class %s routes to __invoke, but the class has no __invoke method. Put the attribute on a method instead.',
                class_basename(static::class),
                $class->getName(),
            ));
        }

        return [new RouteDefinition($this->methods, $this->uri, $class->getName(), $action)];
    }

    public function modify(RouteDefinition $route): void
    {
        if ($this->name !== null) {
            $route->name($this->name);
        }

        $route->middleware($this->middleware)
            ->withoutMiddleware($this->withoutMiddleware)
            ->wheres($this->where)
            ->defaults($this->defaults);

        if ($this->domain !== null) {
            $route->domain($this->domain);
        }

        if ($this->priority !== null) {
            $route->priority($this->priority);
        }

        if ($this->scopeBindings !== null) {
            $route->scopeBindings($this->scopeBindings);
        }

        if ($this->withTrashed !== null) {
            $route->withTrashed($this->withTrashed);
        }
    }
}
