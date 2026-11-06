<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Unit;

use Atannex\Routing\Definition\RouteDefinition;
use Atannex\Routing\Registration\Specificity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpecificityTest extends TestCase
{
    /** @return array<string, array{0: string, 1: array<string, string>, 2: int}> */
    public static function segments(): array
    {
        return [
            'static' => ['docs', [], Specificity::STATIC],
            'value list' => ['{desk}', ['desk' => 'news|politics|help-center'], Specificity::VALUE_LIST],
            'constrained' => ['{id}', ['id' => '[0-9]+'], Specificity::CONSTRAINED],
            'mixed segment' => ['{name}.xml', [], Specificity::CONSTRAINED],
            'open' => ['{slug}', [], Specificity::OPEN],
            'optional' => ['{page?}', [], Specificity::OPTIONAL],
            'slash in pattern' => ['{path}', ['path' => '[a-z]+(?:/[a-z]+)*'], Specificity::SPANNING],
            'dot star' => ['{any}', ['any' => '.*'], Specificity::SPANNING],
            'negated class' => ['{any}', ['any' => '[^.]+'], Specificity::SPANNING],
            'escaped dot is fine' => ['{token}', ['token' => '[a-z]+\.[a-z]+'], Specificity::CONSTRAINED],
            'negated slash is fine' => ['{seg}', ['seg' => '[^/]+'], Specificity::CONSTRAINED],
        ];
    }

    /** @param array<string, string> $wheres */
    #[DataProvider('segments')]
    public function test_segment_ranks(string $segment, array $wheres, int $rank): void
    {
        $this->assertSame($rank, Specificity::rank($segment, $wheres));
    }

    public function test_sorting(): void
    {
        $routes = [
            $this->route('{path}', ['path' => '.*'], 0),
            $this->route('{desk}', ['desk' => 'news|sport'], 1),
            $this->route('docs/{slug}', [], 2),
            $this->route('docs/testimonials', [], 3),
            $this->route('about', [], 4),
            $this->route('docs/{type}', ['type' => 'faq|terms'], 5),
        ];

        usort($routes, [Specificity::class, 'compare']);

        $this->assertSame(
            ['docs/testimonials', 'docs/{type}', 'docs/{slug}', 'about', '{desk}', '{path}'],
            array_map(fn (RouteDefinition $route) => $route->uri(), $routes),
        );
    }

    public function test_priority_then_domain_then_specificity(): void
    {
        $catchAll = $this->route('{path}', ['path' => '.*'], 0);
        $catchAll->priority = 5;
        $domain = $this->route('{x}', [], 1);
        $domain->domain = 'api.test';
        $static = $this->route('about', [], 2);

        $routes = [$static, $domain, $catchAll];
        usort($routes, [Specificity::class, 'compare']);

        $this->assertSame([$catchAll, $domain, $static], $routes);
    }

    public function test_sort_gives_the_same_order_as_compare(): void
    {
        mt_srand(7);
        $segments = ['docs', '{id}', '{slug}', '{page?}', '{path}', '{desk}', '{name}.xml', 'api'];
        $wheres = ['id' => '[0-9]+', 'path' => '.*', 'desk' => 'news|sport'];

        for ($run = 0; $run < 200; $run++) {
            $routes = [];

            for ($i = 0, $count = mt_rand(1, 30); $i < $count; $i++) {
                $path = [];

                for ($j = 0, $length = mt_rand(0, 4); $j < $length; $j++) {
                    $path[] = $segments[mt_rand(0, count($segments) - 1)];
                }

                $route = new RouteDefinition(['GET'], implode('/', $path), 'C', 'm', mt_rand(0, 15) ? RouteDefinition::KIND_ROUTE : RouteDefinition::KIND_FALLBACK);
                $route->wheres = $wheres;
                $route->priority = [0, 0, 0, 1, -1][mt_rand(0, 4)];
                $route->domain = mt_rand(0, 5) ? null : 'api.test';
                // Some share a sequence, as redirects share their target's.
                $route->sequence = mt_rand(0, 3) ? $i : max(0, $i - 1);
                $routes[] = $route;
            }

            $expected = $routes;
            usort($expected, [Specificity::class, 'compare']);

            $this->assertSame($expected, Specificity::sort($routes));
        }
    }

    /** @param array<string, string> $wheres */
    private function route(string $uri, array $wheres, int $sequence): RouteDefinition
    {
        $route = new RouteDefinition(['GET'], $uri, 'C');
        $route->wheres = $wheres;
        $route->sequence = $sequence;

        return $route;
    }
}
