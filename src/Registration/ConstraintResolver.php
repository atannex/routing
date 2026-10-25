<?php

declare(strict_types=1);

namespace Atannex\Routing\Registration;

use Atannex\Routing\Exceptions\RoutingException;
use BackedEnum;
use Illuminate\Contracts\Container\Container;
use UnitEnum;

/**
 * Turns constraint specs into the regexes the router uses.
 *
 * A spec is a regex string, "@name" for a named pattern (config
 * routing.patterns, which may itself be a [class, method] callable), or an
 * array from WhereIn: ['in' => [...]], ['config' => key, 'keys' => bool],
 * ['enum' => class] or ['using' => [class, method], 'keys' => bool].
 */
final class ConstraintResolver
{
    /** Matches nothing: for a value list that turned out empty. */
    public const NOTHING = '(?!)';

    /** @var array<string, string> */
    private array $resolved = [];

    /**
     * @param  array<string, string|array{0: class-string, 1: string}>  $patterns
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $patterns = [],
    ) {}

    /**
     * @param  string|array<string, mixed>  $spec
     */
    public function resolve(string|array $spec): string
    {
        if (is_string($spec)) {
            return str_starts_with($spec, '@') ? $this->pattern(substr($spec, 1)) : $spec;
        }

        return $this->alternatives(match (true) {
            array_key_exists('in', $spec) => (array) $spec['in'],
            array_key_exists('config', $spec) => $this->listFrom($this->container->make('config')->get($spec['config'], []), (bool) ($spec['keys'] ?? false)),
            array_key_exists('enum', $spec) => $this->enumValues($spec['enum']),
            array_key_exists('using', $spec) => $this->listFrom($this->container->call($spec['using']), (bool) ($spec['keys'] ?? false)),
            default => throw new RoutingException('Unknown constraint spec: '.json_encode($spec)),
        });
    }

    /** A named pattern from config routing.patterns. */
    public function pattern(string $name): string
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        if (! array_key_exists($name, $this->patterns)) {
            throw new RoutingException("Unknown route pattern \"@{$name}\". Add it to config/routing.php under \"patterns\".");
        }

        $pattern = $this->patterns[$name];
        $regex = is_array($pattern) ? (string) $this->container->call($pattern) : (string) $pattern;

        return $this->resolved[$name] = $this->resolve($regex);
    }

    /**
     * @return array<string, string>
     */
    public function patterns(): array
    {
        $all = [];

        foreach (array_keys($this->patterns) as $name) {
            $all[$name] = $this->pattern($name);
        }

        return $all;
    }

    /**
     * @param  iterable<mixed>  $values
     */
    public function alternatives(iterable $values): string
    {
        $quoted = [];

        foreach ($values as $value) {
            $value = $value instanceof BackedEnum ? $value->value : ($value instanceof UnitEnum ? $value->name : $value);
            // Hyphens are literal outside character classes; leave them readable.
            $quoted[] = str_replace('\-', '-', preg_quote((string) $value, '#'));
        }

        return $quoted === [] ? self::NOTHING : implode('|', array_unique($quoted));
    }

    /**
     * @return list<mixed>
     */
    private function listFrom(mixed $value, bool $keys): array
    {
        $array = $value instanceof \Traversable ? iterator_to_array($value) : (array) $value;

        return $keys ? array_keys($array) : array_values($array);
    }

    /**
     * @return list<mixed>
     */
    private function enumValues(string $class): array
    {
        if (is_subclass_of($class, UnitEnum::class)) {
            return $class::cases();
        }

        if (method_exists($class, 'getValues')) {
            return array_values($class::getValues());
        }

        throw new RoutingException("{$class} is neither a PHP enum nor a class with a static getValues().");
    }
}
