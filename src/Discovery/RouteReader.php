<?php

declare(strict_types=1);

namespace Atannex\Routing\Discovery;

use Atannex\Routing\Attributes\NoRoutes;
use Atannex\Routing\Contracts\DeclaresRoutes;
use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Atannex\Routing\Exceptions\RoutingException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Turns one controller class into route definitions.
 *
 * Declarations (Get, Post, Resource, Fallback...) come from the class itself
 * and its public methods, including routed methods inherited from abstract
 * parents. Modifiers are applied from the outside in: the directory's
 * options, the parent classes (outermost first), the class, the method, and
 * finally the declaration's own options.
 */
final class RouteReader
{
    public function __construct(private readonly ?string $basePath = null) {}

    /**
     * @param  class-string  $class
     * @param  list<RouteModifier>  $outer  modifiers from the directory
     * @return list<RouteDefinition>
     */
    public function read(string $class, array $outer = []): array
    {
        $reflection = new ReflectionClass($class);

        if (! $reflection->isInstantiable() || $reflection->getAttributes(NoRoutes::class) !== []) {
            return [];
        }

        $classModifiers = [];
        foreach (array_reverse($this->lineage($reflection)) as $ancestor) {
            array_push($classModifiers, ...$this->modifiers($ancestor));
        }

        $routes = [];

        foreach ($this->declarations($reflection) as $declaration) {
            foreach ($declaration->routes($reflection, null) as $route) {
                $routes[] = $this->finish($route, $declaration, $reflection, null, $outer, $classModifiers);
            }
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isConstructor() || ! $this->owns($reflection, $method)) {
                continue;
            }

            foreach ($this->declarations($method) as $declaration) {
                foreach ($declaration->routes($reflection, $method) as $route) {
                    $routes[] = $this->finish($route, $declaration, $reflection, $method, $outer, $classModifiers);
                }
            }
        }

        return $routes;
    }

    /**
     * @param  ReflectionClass<object>  $class
     * @param  list<RouteModifier>  $outer
     * @param  list<RouteModifier>  $classModifiers
     */
    private function finish(
        RouteDefinition $route,
        DeclaresRoutes $declaration,
        ReflectionClass $class,
        ?ReflectionMethod $declaredOn,
        array $outer,
        array $classModifiers,
    ): RouteDefinition {
        $target = $class->hasMethod($route->action) ? $class->getMethod($route->action) : null;

        // Middleware the declaration itself added (a resource's can:...) comes
        // after the directory's, and is not part of the base redirects get.
        $declared = $route->middleware;
        $route->middleware = [];

        foreach ($outer as $modifier) {
            $modifier->modify($route);
        }

        $route->baseMiddleware = $route->middleware;
        $route->middleware($declared);

        foreach ([...$classModifiers, ...($target ? $this->modifiers($target) : [])] as $modifier) {
            $modifier->modify($route);
        }

        if ($declaration instanceof RouteModifier) {
            $declaration->modify($route);
        }

        $line = ($declaredOn ?? $class)->getStartLine();
        $route->source = $this->relative((string) ($declaredOn ?? $class)->getFileName()).':'.$line;

        return $route;
    }

    /**
     * Inherited methods count only when they come from an abstract parent or
     * a trait; a concrete parent registers its own routes.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function owns(ReflectionClass $class, ReflectionMethod $method): bool
    {
        $declaring = $method->getDeclaringClass();

        return $declaring->getName() === $class->getName() || $declaring->isAbstract();
    }

    /**
     * @param  ReflectionClass<object>  $class
     * @return list<ReflectionClass<object>> the class first, then its parents
     */
    private function lineage(ReflectionClass $class): array
    {
        $lineage = [];

        for ($current = $class; $current !== false; $current = $current->getParentClass()) {
            $lineage[] = $current;
        }

        return $lineage;
    }

    /**
     * @param  ReflectionClass<object>|ReflectionMethod  $reflector
     * @return list<DeclaresRoutes>
     */
    private function declarations(ReflectionClass|ReflectionMethod $reflector): array
    {
        return $this->instantiate($reflector, DeclaresRoutes::class);
    }

    /**
     * @param  ReflectionClass<object>|ReflectionMethod  $reflector
     * @return list<RouteModifier>
     */
    private function modifiers(ReflectionClass|ReflectionMethod $reflector): array
    {
        return array_values(array_filter(
            $this->instantiate($reflector, RouteModifier::class),
            fn (RouteModifier $modifier) => ! $modifier instanceof DeclaresRoutes,
        ));
    }

    /**
     * @template T of object
     *
     * @param  ReflectionClass<object>|ReflectionMethod  $reflector
     * @param  class-string<T>  $type
     * @return list<T>
     */
    private function instantiate(ReflectionClass|ReflectionMethod $reflector, string $type): array
    {
        $instances = [];

        foreach ($reflector->getAttributes($type, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            try {
                $instances[] = $attribute->newInstance();
            } catch (RoutingException $e) {
                throw $e;
            } catch (Throwable $e) {
                $where = $reflector instanceof ReflectionMethod
                    ? $reflector->getDeclaringClass()->getName().'::'.$reflector->getName()
                    : $reflector->getName();

                throw new RoutingException("Invalid #[{$attribute->getName()}] on {$where}: {$e->getMessage()}", 0, $e);
            }
        }

        return $instances;
    }

    private function relative(string $path): string
    {
        if ($this->basePath !== null && str_starts_with($path, $this->basePath)) {
            return ltrim(substr($path, strlen($this->basePath)), '/');
        }

        return $path;
    }
}
