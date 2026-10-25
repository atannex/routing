<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Unit;

use Atannex\Routing\Definition\RouteDefinition;
use Atannex\Routing\Discovery\ClassFinder;
use Atannex\Routing\Registration\RouteRegistrar;
use PHPUnit\Framework\TestCase;

final class DiscoveryTest extends TestCase
{
    public function test_class_names_are_read_from_the_source(): void
    {
        $finder = new ClassFinder;

        $this->assertSame('App\Http\Foo', $finder->classIn("<?php\nnamespace App\\Http;\n\n#[X]\nfinal readonly class Foo {}"));
        $this->assertSame('Bar', $finder->classIn("<?php\nabstract class Bar {}"));
        $this->assertNull($finder->classIn("<?php\nreturn ['class' => 1];"));
    }

    public function test_finding_respects_patterns_and_excludes(): void
    {
        $classes = (new ClassFinder)->find(__DIR__.'/../Fixtures/Controllers', ['*Controller.php'], ['Admin/*']);

        $this->assertContains('Atannex\Routing\Tests\Fixtures\Controllers\AboutController', $classes);
        $this->assertNotContains('Atannex\Routing\Tests\Fixtures\Controllers\Admin\UsersController', $classes);
        $this->assertSame([], (new ClassFinder)->find('/no/such/dir'));
    }

    public function test_names_from_the_uri(): void
    {
        $this->assertSame('sports.leagues.table', RouteRegistrar::nameFromUri(new RouteDefinition(['GET'], '/sports/leagues/{league}/table', 'C')));
        $this->assertSame('index', RouteRegistrar::nameFromUri(new RouteDefinition(['GET'], '/', 'C')));
        $this->assertSame('photos.store', RouteRegistrar::nameFromUri(new RouteDefinition(['POST'], 'photos', 'C')));
        $this->assertSame('photos.destroy', RouteRegistrar::nameFromUri(new RouteDefinition(['DELETE'], 'photos/{photo}', 'C')));
    }

    public function test_definitions_survive_serialization(): void
    {
        $route = new RouteDefinition(['get', 'post'], 'a/{b}', 'C', 'm');
        $route->prefix('x')->as('x.')->name('a')->middleware(['web', 'web', 'auth'])->where('b', ['in' => [1, 2]]);

        $copy = RouteDefinition::fromArray($route->toArray());

        $this->assertSame($route->toArray(), $copy->toArray());
        $this->assertSame(['GET', 'POST'], $copy->methods);
        $this->assertSame('x/a/{b}', $copy->uri());
        $this->assertSame('x.a', $copy->fullName());
        $this->assertSame(['web', 'auth'], $copy->middleware);
        $this->assertSame('C@m', $copy->uses());
    }
}
