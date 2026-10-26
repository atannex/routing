<?php

declare(strict_types=1);

namespace Atannex\Routing\Definition;

/**
 * One route, as declared by the attributes, before it reaches the router.
 *
 * Attributes are applied from the outside in: the directory, the parent
 * classes, the class, the method, and the route attribute's own options
 * last. So prefixes, name prefixes and middleware accumulate in that order,
 * and for everything else (constraints, defaults, domain, priority...) the
 * innermost value wins.
 *
 * Every property holds plain data, so a definition can be written to the
 * discovery cache and read back (toArray / fromArray).
 */
final class RouteDefinition
{
    public const KIND_ROUTE = 'route';

    public const KIND_REDIRECT = 'redirect';

    public const KIND_FALLBACK = 'fallback';

    /** URI prefix accumulated from groups, without slashes at either end. */
    public string $prefix = '';

    /** Name prefix accumulated from groups (`as`). */
    public string $namePrefix = '';

    public ?string $name = null;

    /** @var list<string> */
    public array $middleware = [];

    /** @var list<string> */
    public array $withoutMiddleware = [];

    /** Middleware from the directory alone; redirects to this route get only these. @var list<string> */
    public array $baseMiddleware = [];

    /**
     * Constraint specs per parameter: a regex string, "@named-pattern", or an
     * array spec resolved at registration (see ConstraintResolver).
     *
     * @var array<string, string|array<string, mixed>>
     */
    public array $wheres = [];

    /** @var array<string, mixed> */
    public array $defaults = [];

    public ?string $domain = null;

    public int $priority = 0;

    public ?bool $scopeBindings = null;

    public bool $withTrashed = false;

    /** Implicit binding fields per parameter, like {post:slug}. @var array<string, string> */
    public array $bindingFields = [];

    /** Inline rate limits, all applied: [['limit' => int, 'minutes' => int, 'by' => string], ...]. @var list<array<string, mixed>>|null */
    public ?array $throttle = null;

    /** Webhook signature check: ['secret' => config key, 'header' => ..., 'algorithm' => ..., 'prefix' => ...]. @var array<string, mixed>|null */
    public ?array $webhook = null;

    /** Session blocking (Route::block()): [lockSeconds, waitSeconds]. @var array{0: int|null, 1: int|null}|null */
    public ?array $block = null;

    /**
     * Registration conditions: [['env', [...]], ['config', key, value], ['using', [class, method]]].
     *
     * @var list<array<int, mixed>>
     */
    public array $conditions = [];

    /**
     * Request input validation: ['rules' => [...]|class-string, 'messages' => [], 'attributes' => []].
     *
     * @var array<string, mixed>|null
     */
    public ?array $validate = null;

    /**
     * Route parameter rules: ['year' => 'integer|min:1990'], with the status
     * returned when one fails.
     *
     * @var array<string, mixed>
     */
    public array $parameterRules = [];

    public int $parameterStatus = 404;

    /** @var array<string, mixed>|null */
    public ?array $deprecated = null;

    /** @var array<string, mixed>|null ['redirect' => name] or ['handler' => [class, method]] */
    public ?array $missing = null;

    /** Old addresses that redirect here: [[uri, status]]. @var list<array{0: string, 1: int}> */
    public array $redirectsFrom = [];

    /** For KIND_REDIRECT: [destination, status]. @var array{0: string, 1: int}|null */
    public ?array $redirect = null;

    /** Free-form data custom attributes can attach; ends up in the route action under "routing.meta". @var array<string, mixed> */
    public array $meta = [];

    /** Where it was declared, "path/to/File.php:12". */
    public string $source = '';

    /** Position in discovery; keeps the sort stable. */
    public int $sequence = 0;

    /**
     * @param  list<string>  $methods
     */
    public function __construct(
        public array $methods,
        public string $path,
        public string $controller,
        public string $action = '__invoke',
        public string $kind = self::KIND_ROUTE,
    ) {
        $this->methods = array_values(array_unique(array_map('strtoupper', $methods)));
    }

    /* ------------------------------------------------------------------
     | Fluent API for modifiers (RouteModifier::modify)
     * ------------------------------------------------------------------ */

    public function prefix(string $prefix): self
    {
        $prefix = trim($prefix, '/');

        if ($prefix !== '') {
            $this->prefix = trim($this->prefix.'/'.$prefix, '/');
        }

        return $this;
    }

    public function as(string $namePrefix): self
    {
        $this->namePrefix .= $namePrefix;

        return $this;
    }

    public function name(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param  string|list<string>  $middleware
     */
    public function middleware(string|array $middleware): self
    {
        foreach ((array) $middleware as $item) {
            if ($item !== '' && ! in_array($item, $this->middleware, true)) {
                $this->middleware[] = $item;
            }
        }

        return $this;
    }

    /**
     * @param  string|list<string>  $middleware
     */
    public function withoutMiddleware(string|array $middleware): self
    {
        foreach ((array) $middleware as $item) {
            if ($item !== '' && ! in_array($item, $this->withoutMiddleware, true)) {
                $this->withoutMiddleware[] = $item;
            }
        }

        return $this;
    }

    /**
     * @param  string|array<string, mixed>  $constraint
     */
    public function where(string $parameter, string|array $constraint): self
    {
        $this->wheres[$parameter] = $constraint;

        return $this;
    }

    /**
     * @param  array<string, string|array<string, mixed>>  $wheres
     */
    public function wheres(array $wheres): self
    {
        foreach ($wheres as $parameter => $constraint) {
            $this->where((string) $parameter, $constraint);
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    public function defaults(array $defaults): self
    {
        $this->defaults = array_replace($this->defaults, $defaults);

        return $this;
    }

    public function domain(?string $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function scopeBindings(?bool $scope = true): self
    {
        $this->scopeBindings = $scope;

        return $this;
    }

    public function withTrashed(bool $withTrashed = true): self
    {
        $this->withTrashed = $withTrashed;

        return $this;
    }

    /** Binds a parameter by another column: bind('post', 'slug') is {post:slug}. */
    public function bind(string $parameter, string $field): self
    {
        $this->bindingFields[$parameter] = $field;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $condition
     */
    public function condition(array $condition): self
    {
        $this->conditions[] = $condition;

        return $this;
    }

    public function meta(string $key, mixed $value): self
    {
        $this->meta[$key] = $value;

        return $this;
    }

    /* ------------------------------------------------------------------
     | Reading
     * ------------------------------------------------------------------ */

    /** The full URI, prefix included, as the router expects it. */
    public function uri(): string
    {
        $uri = trim($this->prefix.'/'.trim($this->path, '/'), '/');

        return $uri === '' ? '/' : $uri;
    }

    /** The full name, name prefix included; null when the route is unnamed. */
    public function fullName(): ?string
    {
        return $this->name === null ? null : $this->namePrefix.$this->name;
    }

    /** "Controller@method", or the class alone for invokable controllers. */
    public function uses(): string
    {
        return $this->action === '__invoke' ? $this->controller : $this->controller.'@'.$this->action;
    }

    /**
     * Parameter names in the URI (and the domain), in order.
     *
     * @return list<string>
     */
    public function parameterNames(): array
    {
        preg_match_all('/\{(\w+?)(?::\w+)?\??\}/', ($this->domain ?? '').'/'.$this->uri(), $matches);

        return array_values(array_unique($matches[1]));
    }

    /** "Class::method", for listings and error messages. */
    public function handler(): string
    {
        return $this->controller.'::'.$this->action;
    }

    /* ------------------------------------------------------------------
     | Serialization (discovery cache)
     * ------------------------------------------------------------------ */

    /** Property defaults of a fresh definition, left out of the cache. @var array<string, mixed>|null */
    private static ?array $blank = null;

    /**
     * The properties that differ from a fresh definition, plus the
     * constructor arguments: a much smaller cache file to load.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        self::$blank ??= get_object_vars(new self([], '', ''));

        $data = ['methods' => $this->methods, 'path' => $this->path, 'controller' => $this->controller];

        foreach (get_object_vars($this) as $key => $value) {
            if (! array_key_exists($key, $data) && $value !== self::$blank[$key]) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $definition = new self($data['methods'], $data['path'], $data['controller'], $data['action'] ?? '__invoke', $data['kind'] ?? self::KIND_ROUTE);

        foreach ($data as $key => $value) {
            if (property_exists($definition, $key)) {
                $definition->{$key} = $value;
            }
        }

        return $definition;
    }
}
