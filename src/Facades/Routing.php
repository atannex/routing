<?php

declare(strict_types=1);

namespace Atannex\Routing\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Atannex\Routing\Routing directory(string $path, array $options = [])
 * @method static \Atannex\Routing\Routing controller(string|array $classes, array $options = [])
 * @method static array directories()
 * @method static list<\Atannex\Routing\Definition\RouteDefinition> definitions()
 * @method static list<\Atannex\Routing\Definition\RouteDefinition> discover()
 * @method static list<\Atannex\Routing\Definition\RouteDefinition> routes()
 * @method static list<\Illuminate\Routing\Route> register(?\Illuminate\Routing\Router $router = null)
 * @method static bool isRegistered()
 * @method static bool isCached()
 * @method static int cache()
 * @method static bool clearCache()
 * @method static void flush()
 *
 * @see \Atannex\Routing\Routing
 */
class Routing extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Atannex\Routing\Routing::class;
    }
}
