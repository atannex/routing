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
 * A resource controller on the class: the usual seven routes, for the
 * methods the controller actually has.
 *
 *     #[Resource('photos')]                          // photos.index … photos.destroy
 *     #[Resource('users.photos', shallow: true)]     // /users/{user}/photos, /photos/{photo}
 *     #[Resource('photos', only: ['index', 'show'], parameters: ['photos' => 'image'])]
 *     #[Resource('posts', authorize: Post::class)]   // each action checks PostPolicy (viewAny, view, create, update, delete)
 *     #[Resource('users.posts', scoped: ['post' => 'slug'])]   // /users/{user}/posts/{post:slug}
 *
 * Method attributes (middleware, constraints, validation...) on index,
 * store, etc. apply to their route.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Resource implements DeclaresRoutes
{
    /** action => [methods, path template, needs the item parameter] */
    protected const ACTIONS = [
        'index' => [['GET'], '', false],
        'create' => [['GET'], 'create', false],
        'store' => [['POST'], '', false],
        'show' => [['GET'], '{item}', true],
        'edit' => [['GET'], '{item}/edit', true],
        'update' => [['PUT', 'PATCH'], '{item}', true],
        'destroy' => [['DELETE'], '{item}', true],
    ];

    /** @var list<string> */
    protected const API_EXCLUDED = ['create', 'edit'];

    /** Policy ability per action, as Laravel's authorizeResource() used. */
    protected const ABILITIES = [
        'index' => 'viewAny',
        'create' => 'create',
        'store' => 'create',
        'show' => 'view',
        'edit' => 'update',
        'update' => 'update',
        'destroy' => 'delete',
    ];

    /**
     * @param  list<string>|null  $only
     * @param  list<string>  $except
     * @param  string|array<string, string>|null  $names  a name base, or names per action
     * @param  array<string, string>  $parameters  resource segment => parameter name
     */
    public function __construct(
        public string $name,
        public ?array $only = null,
        public array $except = [],
        public string|array|null $names = null,
        public array $parameters = [],
        public bool $shallow = false,
        public bool $api = false,
        public ?string $authorize = null,
        public array $scoped = [],
    ) {}

    public function routes(ReflectionClass $class, ?ReflectionMethod $method): array
    {
        $segments = explode('.', $this->name);
        $last = array_pop($segments);

        $parents = '';
        foreach ($segments as $segment) {
            $parents .= $segment.'/{'.$this->parameter($segment).'}/';
        }

        $item = '{'.$this->parameter($last).'}';
        $routes = [];

        foreach (static::ACTIONS as $action => [$methods, $template, $needsItem]) {
            if (! $this->wants($action) || ! $class->hasMethod($action)) {
                continue;
            }

            $base = ($this->shallow && $needsItem) ? '' : $parents;
            $path = rtrim($base.$last.'/'.str_replace('{item}', $item, $template), '/');

            $route = new RouteDefinition($methods, $path, $class->getName(), $action);
            $route->name($this->routeName($action));

            foreach ($this->scoped as $parameter => $field) {
                $route->bind($parameter, $field);
            }

            if ($this->authorize !== null) {
                $route->middleware('can:'.static::ABILITIES[$action].','.($needsItem ? $this->parameter($last) : $this->authorize));
            }

            $routes[] = $route;
        }

        return $routes;
    }

    protected function wants(string $action): bool
    {
        if ($this->api && in_array($action, static::API_EXCLUDED, true)) {
            return false;
        }

        return ($this->only === null || in_array($action, $this->only, true))
            && ! in_array($action, $this->except, true);
    }

    protected function parameter(string $segment): string
    {
        return $this->parameters[$segment] ?? str_replace('-', '_', Str::singular($segment));
    }

    protected function routeName(string $action): string
    {
        if (is_array($this->names) && isset($this->names[$action])) {
            return $this->names[$action];
        }

        $base = is_string($this->names) ? $this->names : str_replace('/', '.', $this->name);

        return $base.'.'.$action;
    }
}
