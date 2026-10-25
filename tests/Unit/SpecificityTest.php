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

    /** @param array<string, string> $wheres */
    private function route(string $uri, array $wheres, int $sequence): RouteDefinition
    {
        $route = new RouteDefinition(['GET'], $uri, 'C');
        $route->wheres = $wheres;
        $route->sequence = $sequence;

        return $route;
    }
}
