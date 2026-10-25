<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    |
    | "booted": once the application has booted, after routes/web.php and the
    |           routes of every other package (Fortify, Filament...). That is
    |           where routes/web.php itself sits, so an app catch-all such as
    |           /{path} cannot swallow package routes. The default.
    | "boot":   while this package boots, ahead of routes/web.php and of the
    |           packages booting after it.
    | false:    never on its own; call Routing::register() yourself.
    |
    | Nothing is registered when the routes are cached (php artisan route:cache);
    | the cache already holds them.
    |
    */

    'register' => env('ROUTING_REGISTER', 'booted'),

    /*
    |--------------------------------------------------------------------------
    | Directories
    |--------------------------------------------------------------------------
    |
    | Where to look for controllers, relative to the project root, each with
    | the group options its routes get: prefix, as, middleware,
    | withoutMiddleware, domain, where, defaults, scopeBindings, priority.
    | "patterns" filters file names and "exclude" skips paths (globs,
    | relative to the directory).
    |
    */

    'directories' => [
        'app/Http/Controllers' => [
            'middleware' => ['web'],
        ],

        // An API in its own folder: the deepest directory owns its classes,
        // so these get the api group (stateless, no CSRF) and not web.
        // 'app/Http/Controllers/Api' => [
        //     'prefix' => 'api',
        //     'as' => 'api.',
        //     'middleware' => ['api'],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Named patterns
    |--------------------------------------------------------------------------
    |
    | Reusable constraints, used as "@name": #[Where('slug', '@slug')]. A value
    | is a regex, or a [class, method] callable returning one (called through
    | the container when the routes are registered).
    |
    */

    'patterns' => [
        'id' => '[0-9]+',
        'slug' => '[A-Za-z0-9\-]+',
        'slug-path' => '[A-Za-z0-9\-]+(?:/[A-Za-z0-9\-]+)*',
        'year' => '\d{4}',
        'month' => '0[1-9]|1[0-2]',
        'day' => '0[1-9]|[12][0-9]|3[01]',
        'uuid' => '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}',
        'ulid' => '[0-7][0-9a-hjkmnp-tv-zA-HJKMNP-TV-Z]{25}',
        'locale' => '[a-z]{2}(?:[_-][A-Za-z]{2,4})?',
    ],

    /*
    |--------------------------------------------------------------------------
    | Parameter constraints by name
    |--------------------------------------------------------------------------
    |
    | Applied to every attribute route with a parameter of that name that has
    | no constraint of its own, e.g. 'match' => '@id'.
    |
    */

    'parameters' => [],

    /*
    |--------------------------------------------------------------------------
    | Automatic names
    |--------------------------------------------------------------------------
    |
    | null leaves unnamed routes unnamed. "uri" names them from the path
    | ("sports/leagues/{league}/table" => "sports.leagues.table", with
    | ".store", ".update" or ".destroy" for other verbs). A [class, method]
    | callable receives the RouteDefinition as $route and returns a name.
    |
    */

    'naming' => null,

    /*
    |--------------------------------------------------------------------------
    | Ordering
    |--------------------------------------------------------------------------
    |
    | "specificity": most specific first (static before parameters, value
    | lists before patterns, catch-alls last), wherever they are declared.
    | "declaration": discovery order (file name order). #[Priority] and
    | fallbacks are honoured either way.
    |
    */

    'ordering' => 'specificity',

    /*
    |--------------------------------------------------------------------------
    | Discovery cache
    |--------------------------------------------------------------------------
    |
    | php artisan routing:cache writes the discovered routes here so requests
    | skip reflection (php artisan optimize does it too). Defaults to
    | bootstrap/cache/routing.php.
    |
    */

    'cache' => env('ROUTING_CACHE'),

    /*
    |--------------------------------------------------------------------------
    | Pages and redirects without a controller
    |--------------------------------------------------------------------------
    |
    | "views" renders a Blade view (Route::view): 'terms' => 'pages.terms',
    | or 'about' => ['view' => 'pages.about', 'name' => 'about', 'data' => [...]].
    | "redirects" sends an old address on (Route::redirect): '/old' => '/new',
    | or '/old' => ['to' => '/new', 'status' => 302]. Both take middleware,
    | name, domain and where; middleware defaults to "static_middleware".
    | They are ordered and listed with the attribute routes.
    |
    */

    'views' => [],

    'redirects' => [],

    'static_middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Model bindings
    |--------------------------------------------------------------------------
    |
    | Explicit bindings by parameter name, for every route (Route::model /
    | Route::bind): 'author' => User::class, or a resolver receiving $value
    | and $route: 'post' => [PostResolver::class, 'resolve']. Use #[Bind]
    | for a different column on some routes only.
    |
    */

    'bindings' => [],

    /*
    |--------------------------------------------------------------------------
    | Scheduling
    |--------------------------------------------------------------------------
    |
    | Commands, jobs and invokable classes in these directories that carry
    | #[Schedule] are added to Laravel's scheduler, next to whatever
    | routes/console.php schedules. php artisan schedule:list shows them.
    |
    */

    'schedule' => [
        'enabled' => env('ROUTING_SCHEDULE', true),
        'directories' => [
            'app/Console/Commands',
            'app/Jobs',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast channels
    |--------------------------------------------------------------------------
    |
    | Channel classes carrying #[Channel('orders.{order}')] in these
    | directories are registered with the broadcaster, next to whatever
    | routes/channels.php declares.
    |
    */

    'channels' => [
        'enabled' => env('ROUTING_CHANNELS', true),
        'directories' => [
            'app/Broadcasting',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Console commands
    |--------------------------------------------------------------------------
    |
    | Extra directories whose commands are registered with Artisan. Laravel
    | already loads app/Console/Commands; add modules or src/ folders here.
    |
    */

    'commands' => [],

];
