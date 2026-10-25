<?php

declare(strict_types=1);

namespace Atannex\Routing\Support;

use Illuminate\Routing\Route;

/**
 * Builds a concrete URL a route accepts, by filling each parameter with the
 * first candidate value its constraint allows. Used by routing:lint to find
 * routes that an earlier route would swallow.
 */
final class SampleUri
{
    private const CANDIDATES = ['sample', 'sample-value', '1', '7', '2024', '05', 'a', 'A1', 'x.y', 'a/b',
        '00000000-0000-4000-8000-000000000000', '01ARZ3NDEKTSV4RRFFQ69G5FAV'];

    /** The path (no leading slash), or null when no candidate fits a parameter. */
    public static function for(Route $route): ?string
    {
        $failed = false;

        $uri = preg_replace_callback('/\{(\w+)(?::\w+)?(\?)?\}/', function (array $match) use ($route, &$failed) {
            $pattern = $route->wheres[$match[1]] ?? '[^/]+';
            $value = self::value($pattern);

            if ($value === null) {
                $failed = true;
            }

            return (string) $value;
        }, $route->uri());

        return $failed ? null : (string) $uri;
    }

    public static function value(string $pattern): ?string
    {
        if (preg_match('/^\(?([A-Za-z0-9_\-]+)(?:\||\)?$)/', $pattern, $literal) && self::fits($pattern, $literal[1])) {
            return $literal[1];
        }

        foreach (self::CANDIDATES as $candidate) {
            if (self::fits($pattern, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function fits(string $pattern, string $value): bool
    {
        return @preg_match('{^(?:'.$pattern.')$}sDu', $value) === 1;
    }
}
