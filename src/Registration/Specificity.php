<?php

declare(strict_types=1);

namespace Atannex\Routing\Registration;

use Atannex\Routing\Definition\RouteDefinition;

/**
 * Orders routes so the most specific one is tried first, wherever it was
 * declared. That removes the "order matters" rule of hand-written route
 * files: a catch-all can live in any controller.
 *
 * Each path segment gets a rank, compared left to right:
 *
 *   5  static          /docs
 *   4  value list      {desk} where desk is news|politics|…
 *   3  constrained     {id} where id is [0-9]+, or a mixed segment like {name}.xml
 *   2  open            {slug}
 *   1  optional        {page?}
 *   0  spans slashes   {path} where path is .* or contains "/"
 *
 * At the first segment where two routes differ, the higher rank wins; when
 * one path is a prefix of the other, the longer goes first. Routes with a
 * domain come before routes without, and #[Priority] beats all of it.
 */
final class Specificity
{
    public const STATIC = 5;

    public const VALUE_LIST = 4;

    public const CONSTRAINED = 3;

    public const OPEN = 2;

    public const OPTIONAL = 1;

    public const SPANNING = 0;

    /**
     * Ranks of a definition whose wheres are already resolved to regexes.
     *
     * @return list<int>
     */
    public static function ranks(RouteDefinition $route): array
    {
        $uri = $route->uri();

        if ($uri === '/') {
            return [];
        }

        return array_map(fn (string $segment) => self::rank($segment, $route->wheres), explode('/', $uri));
    }

    /**
     * @param  array<string, mixed>  $wheres
     */
    public static function rank(string $segment, array $wheres): int
    {
        if (! str_contains($segment, '{')) {
            return self::STATIC;
        }

        preg_match_all('/\{(\w+)(?::\w+)?(\?)?\}/', $segment, $parameters, PREG_SET_ORDER);
        $alone = count($parameters) === 1 && preg_match('/^\{[^}]+\}$/', $segment) === 1;
        $lowest = self::STATIC;

        foreach ($parameters as $parameter) {
            $pattern = $wheres[$parameter[1]] ?? null;
            $pattern = is_string($pattern) ? $pattern : null;

            $rank = match (true) {
                $pattern !== null && self::spans($pattern) => self::SPANNING,
                ($parameter[2] ?? '') === '?' => self::OPTIONAL,
                $pattern === null => $alone ? self::OPEN : self::CONSTRAINED,
                self::isValueList($pattern) => $alone ? self::VALUE_LIST : self::CONSTRAINED,
                default => self::CONSTRAINED,
            };

            $lowest = min($lowest, $rank);
        }

        return $lowest;
    }

    /** Whether a regex can match across "/" (it then eats several segments). */
    public static function spans(string $pattern): bool
    {
        if ($pattern === ConstraintResolver::NOTHING) {
            return false;
        }

        // Character classes: spanning only if they allow "/" explicitly or are negated without it.
        $classes = preg_match_all('/(?<!\\\\)\[(\^?)((?:\\\\.|[^\]\\\\])*)\]/', $pattern, $matches, PREG_SET_ORDER);
        foreach ($matches as $class) {
            $allowsSlash = str_contains($class[2], '/');
            if (($class[1] === '' && $allowsSlash) || ($class[1] === '^' && ! $allowsSlash)) {
                return true;
            }
        }

        $outside = (string) preg_replace('/(?<!\\\\)\[(?:\\\\.|[^\]\\\\])*\]/', '', $pattern);

        return str_contains($outside, '/')
            || preg_match('/(?<!\\\\)\./', $outside) === 1
            || preg_match('/\\\\[SWDHV]/', $outside) === 1;
    }

    /** A plain alternation of literals: news|politics|help-center. */
    public static function isValueList(string $pattern): bool
    {
        return preg_match('/^\(?(?:[\w\-]|\\\\.)+(?:\|(?:[\w\-]|\\\\.)+)*\)?$/', $pattern) === 1;
    }

    /**
     * Sort comparator: negative when $a must be registered before $b.
     */
    public static function compare(RouteDefinition $a, RouteDefinition $b): int
    {
        if ($a->kind === RouteDefinition::KIND_FALLBACK || $b->kind === RouteDefinition::KIND_FALLBACK) {
            return ($a->kind === RouteDefinition::KIND_FALLBACK) <=> ($b->kind === RouteDefinition::KIND_FALLBACK)
                ?: $a->sequence <=> $b->sequence;
        }

        if ($a->priority !== $b->priority) {
            return $b->priority <=> $a->priority;
        }

        if (($a->domain === null) !== ($b->domain === null)) {
            return $a->domain === null ? 1 : -1;
        }

        $ranksA = self::ranks($a);
        $ranksB = self::ranks($b);

        foreach ($ranksA as $i => $rank) {
            if (! array_key_exists($i, $ranksB)) {
                break;
            }

            if ($rank !== $ranksB[$i]) {
                return $ranksB[$i] <=> $rank;
            }
        }

        return count($ranksB) <=> count($ranksA) ?: $a->sequence <=> $b->sequence;
    }
}
