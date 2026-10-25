# atannex/routing

[![Tests](https://github.com/atannex/routing/actions/workflows/tests.yml/badge.svg)](https://github.com/atannex/routing/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](composer.json)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012-FF2D20?logo=laravel&logoColor=white)](composer.json)
[![License: MIT](https://img.shields.io/badge/License-MIT-2ea44f.svg)](LICENSE)

Attribute routing for Laravel. Declare each route on its controller, and it registers itself, with everything a route file can do and several things it can't.

```php
use Atannex\Routing\Attributes\{Get, Post, WhereNumber, Throttle, Auth};

#[Get('/sports/matches/{match}', name: 'sports.matches.show', defaults: ['tab' => 'overview'])]
#[Get('/sports/matches/{match}/stats', name: 'sports.matches.stats', defaults: ['tab' => 'stats'])]
#[WhereNumber('match')]
final class MatchController
{
    public function __invoke(SportMatch $match, string $tab) { /* … */ }
}

final class PredictionController
{
    #[Post('/sports/predict/{match}', name: 'sports.predict')]
    #[Auth, Throttle(30, 1), WhereNumber('match')]
    public function store(SportMatch $match) { /* … */ }
}
```

No `routes/web.php` entry, no `use` list of controllers, no "order matters" comments.

## Why this package

|                                  |                                                                                                                                                                                                                                               |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Order-free**                   | Routes are sorted by specificity: `/docs/testimonials` beats `/docs/{type}`, value lists beat open parameters, and catch-alls like `/{path}` go last, whichever controller declares them. `#[Priority]` overrides it.                         |
| **Live constraints**             | `#[WhereIn]` reads its values when routes register: from config, a PHP or bensampo enum, a static method or a constant. Named patterns (`@slug-path`) are defined once.                                                                       |
| **Validation at the route**      | `#[Validate]` validates the input before the controller runs; `#[ValidateParameter]` gives parameters full Laravel rules (`integer\|between:1990,2100`), answering 404 when one fails. |
| **Old addresses**                | `#[RedirectFrom('/welcome')]` sits on the route it points to, and carries parameters over.                                                                                                                                                    |
| **Lifecycle**                    | `#[Deprecated]` sends the standard Deprecation, Sunset and Link headers, and can answer 410 after the sunset.                                                                                                                                 |
| **Conditions**                   | `#[Environment('local')]`, `#[WhenConfig('features.x')]`, `#[When([Class::class, 'method'])]`.                                                                                                                                                |
| **Groups, without the closures** | `#[Group]`, `#[Prefix]` and `#[Name]` on classes, on abstract base controllers (inherited) or per directory in config.                                                                                                                        |
| **No provider boilerplate**      | `#[Throttle(5, 1, by: 'input:email')]` needs no `RateLimiter::for()`; `#[Bind('post', 'slug')]` and `bindings` config replace `Route::bind()`; `#[Resource(authorize: Post::class)]` checks the policy per action; `#[Webhook]` verifies HMAC signatures.     |
| **Beyond web.php**               | `#[Schedule]` replaces `routes/console.php`, `#[Channel]` replaces `routes/channels.php`, and `views`/`redirects` config replace one-line `Route::view()`/`Route::redirect()` entries.                                                       |
| **Extensible**                   | Any attribute implementing `RouteModifier` is a route option, e.g. a project `#[Staff]` that adds three middleware.                                                                                                                           |
| **Tooling**                      | `routing:list` (matching order, file:line), `routing:lint` (duplicates, unreachable routes, unknown middleware, broken redirects), `routing:convert` (prints attributes for an existing route file), a discovery cache wired into `optimize`. |
| **Plain Laravel underneath**     | Routes go through Laravel's router: `route()`, `route:list`, `route:cache`, model binding and middleware all work unchanged.                                                                                                                  |

## Contents

- [atannex/routing](#atannexrouting)
  - [Why this package](#why-this-package)
  - [Contents](#contents)
  - [Installation](#installation)
  - [Declaring routes](#declaring-routes)
  - [Route options](#route-options)
  - [Groups](#groups)
  - [Middleware shorthands](#middleware-shorthands)
  - [Constraints](#constraints)
  - [Ordering](#ordering)
  - [Resources and singletons](#resources-and-singletons)
  - [APIs](#apis)
  - [Rate limits](#rate-limits)
  - [Webhooks](#webhooks)
  - [Model binding](#model-binding)
  - [Redirects and the fallback](#redirects-and-the-fallback)
  - [Pages and redirects without a controller](#pages-and-redirects-without-a-controller)
  - [Validation](#validation)
  - [Deprecation](#deprecation)
  - [Conditions](#conditions)
  - [Missing models](#missing-models)
  - [Custom attributes](#custom-attributes)
  - [Discovery](#discovery)
  - [Scheduling](#scheduling)
  - [Broadcast channels](#broadcast-channels)
  - [Console](#console)
  - [Configuration reference](#configuration-reference)
  - [Attribute reference](#attribute-reference)
  - [Testing](#testing)
  - [License](#license)

## Installation

```bash
composer require atannex/routing
php artisan vendor:publish --tag=routing-config   # optional
```

By default every class under `app/Http/Controllers` is scanned and its routes get the `web` middleware group. Attribute routes register once the application has booted, after `routes/web.php` and the routes of other packages, so an application catch-all never swallows `/login` or `/admin`.

## Declaring routes

`#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]`, `#[Options]`, `#[Any]`, or `#[Route(['GET', 'POST'], '/path')]`.

- **On a method**, the route calls that method.
- **On the class**, it calls `__invoke` (single-action controllers).
- **Repeat** an attribute to give one action several addresses; each keeps its own name and defaults.

```php
#[Get('/home', name: 'home')]
#[Get('/region', name: 'home.region')]
final class HomeController
{
    public function __invoke() { /* … */ }
}
```

Only public, non-static methods with a route attribute are routed. Routed methods inherited from an **abstract** parent are registered for each concrete child (a concrete parent registers its own).

## Route options

Every verb attribute takes the options of a single route. They win over the method's and the class's attributes.

```php
#[Get(
    '/archive/{year}/{month?}',
    name: 'archive',
    middleware: ['cache.headers:public;max_age=300'],
    withoutMiddleware: [SomeMiddleware::class],
    where: ['year' => '@year', 'month' => '@month'],
    defaults: ['month' => null],
    domain: 'news.example.com',
    priority: 0,
    scopeBindings: true,
    withTrashed: false,
)]
```

The same options exist as separate attributes (`#[Middleware]`, `#[Where]`, `#[Defaults]`, `#[Domain]`, `#[Priority]`, `#[ScopeBindings]`, `#[WithTrashed]`), which apply to every route of a method or class.

## Groups

```php
#[Group(prefix: 'account', as: 'account.', middleware: ['auth', 'verified'])]
abstract class AccountController {}

#[Prefix('notifications')]
#[Name('notifications.')]          // name prefix, like Route::name('…')->group()
final class NotificationController extends AccountController
{
    #[Get('/', name: 'index')]       // GET /account/notifications, "account.notifications.index"
    public function index() {}
}
```

Attributes apply from the outside in: the directory's options, parent classes (outermost first), the class, the method, then the route's own options. Prefixes, name prefixes and middleware accumulate in that order; for everything else the innermost value wins. Unnamed routes stay unnamed under a name prefix.

Directory groups live in config:

```php
'directories' => [
    'app/Http/Controllers' => ['middleware' => ['web']],
    'app/Http/Controllers/Api' => ['prefix' => 'api', 'as' => 'api.', 'middleware' => ['api']],
],
```

When directories nest, the deepest one owns its classes.

## Middleware shorthands

| Attribute                                                   | Middleware                                                             |
| ----------------------------------------------------------- | ---------------------------------------------------------------------- |
| `#[Auth]`, `#[Auth('admin')]`                               | `auth`, `auth:admin`                                                   |
| `#[Guest]`                                                  | `guest`                                                                |
| `#[Verified]`                                               | `verified`                                                             |
| `#[Signed]`, `#[Signed(relative: true)]`                    | `signed`, `signed:relative`                                            |
| `#[Throttle(30, 1)]`, `#[Throttle('uploads')]`              | `throttle:30,1`, `throttle:uploads`                                    |
| `#[Can('view', 'advert')]`, `#[Can('create', Post::class)]` | `can:view,advert`                                                      |
| `#[PasswordConfirm]`                                        | `password.confirm`                                                     |
| `#[CacheHeaders(maxAge: 300, public: true, etag: true)]`    | `cache.headers:public;max_age=300;etag`                                |
| `#[WithoutCsrf]`                                            | removes `ValidateCsrfToken` (webhooks, beacons, one-click unsubscribe) |
| `#[Json]`                                                   | errors, 404s and validation failures render as JSON, header or not     |
| `#[Stateless]`                                              | removes session, cookie and CSRF middleware (feeds, pixels, public JSON); don't combine with session-guard `#[Auth]` |
| `#[Webhook(secret: 'services.x.secret')]`                   | stateless, no CSRF, HMAC signature checked ([Webhooks](#webhooks))     |
| `#[Precognitive]`                                           | `HandlePrecognitiveRequests` (Laravel Precognition live validation)    |
| `#[Block(lockSeconds: 10, waitSeconds: 10)]`                | session blocking, like `Route::block()`: one request per session at a time |

## Constraints

```php
#[Where('year', '\d{4}')]
#[Where('path', '@slug-path')]                     // named pattern from config
#[WhereNumber('id')]  #[WhereAlpha('code')]  #[WhereAlphaNumeric('ref')]  #[WhereUuid('id')]  #[WhereUlid('id')]
#[WhereIn('platform', ['facebook', 'x'])]
#[WhereIn('platform', ShareService::PLATFORMS)]   // a constant
#[WhereIn('edition', config: 'editions.editions', keys: true)]
#[WhereIn('status', enum: Status::class)]         // backed enum values, or a class with static getValues()
#[WhereIn('newsletter', using: [Newsletter::class, 'all'])]
```

Value lists are resolved when the routes register, so adding a desk to config needs no route change. Values are regex-quoted (`x.l` matches only `x.l`). An empty list matches nothing.

**Named patterns** are defined once in `config/routing.php`; a value may be a `[class, method]` callable, for patterns built from code:

```php
'patterns' => [
    'slug-path' => '[A-Za-z0-9\-]+(?:/[A-Za-z0-9\-]+)*',
    'document-path' => [RoutePatterns::class, 'documentPath'],
],
```

**Constraints by parameter name** apply to every route with that parameter that has none of its own:

```php
'parameters' => ['match' => '@id', 'team' => '@id'],
```

## Ordering

Laravel tries routes in registration order, which is why route files are full of "keep this above that". This package sorts them instead. Each path segment is ranked, left to right:

| Rank | Segment               | Example                                         |
| ---- | --------------------- | ----------------------------------------------- |
| 5    | static                | `/docs`                                         |
| 4    | value list            | `{desk}` where `news\|politics`                 |
| 3    | constrained, or mixed | `{id}` where `[0-9]+`, `{name}.xml`             |
| 2    | open                  | `{slug}`                                        |
| 1    | optional              | `{page?}`                                       |
| 0    | spans slashes         | `{path}` where `.*` or a pattern containing `/` |

At the first segment where two routes differ, the higher rank goes first; if one path is a prefix of the other, the longer goes first. Routes with a domain come before routes without. **`#[Priority(n)]` beats all of it** (higher first; negative to push a route back). The fallback is always last. `'ordering' => 'declaration'` keeps file order instead.

`php artisan routing:list` shows the resulting order; `routing:lint` warns about any route an earlier one makes unreachable.

## Resources and singletons

```php
#[Resource('photos')]                                   // index, create, store, show, edit, update, destroy
#[Resource('users.photos', shallow: true)]              // /users/{user}/photos, /photos/{photo}
#[Resource('photos', only: ['index', 'show'], names: 'gallery', parameters: ['photos' => 'image'])]
#[ApiResource('posts')]                                 // without create and edit
#[Singleton('profile')]                                 // show, edit, update
#[Singleton('users.avatar', creatable: true)]           // + create, store, destroy
final class PhotoController
{
    #[Middleware('auth')]        // applies to the store route
    public function store() {}
}
```

Only the methods the controller has are routed. Method attributes (middleware, validation...) apply to their action's route.

```php
#[Resource('posts', authorize: Post::class)]               // PostPolicy, per action
#[Resource('users.posts', scoped: ['post' => 'slug'])]     // /users/{user}/posts/{post:slug}
#[ApiResource('posts', authorize: Post::class)]
```

`authorize` adds `can:` middleware with the abilities `authorizeResource()` used: `viewAny` (index), `create` (create, store), `view` (show), `update` (edit, update), `delete` (destroy). The controller needs no `$this->authorize()` calls. Redirects to a resource route don't inherit its authorization.

## APIs

Give the API folder its own directory, as under [Groups](#groups): its controllers get the `api` group (stateless, no CSRF), the `/api` prefix and `api.` names, and not `web`.

```php
namespace App\Http\Controllers\Api;

#[ApiResource('posts', only: ['index', 'show']), Json, Auth('sanctum'), Throttle(60, 1, by: 'user')]
final class PostController { /* … */ }                       // GET /api/posts ⇒ api.posts.index
```

For versions, map `Api/V1` and `Api/V2` to `api/v1` and `api/v2`, and mark the old one `#[Deprecated(sunset: …, successor: 'api.v2.posts.index')]`. For a subdomain, use `'domain' => 'api.example.com'` instead of a prefix.

## Rate limits

`#[Throttle(30, 1)]` and `#[Throttle('uploads')]` are Laravel's `throttle` middleware. Add `by` and no `RateLimiter::for()` is needed:

```php
#[Throttle(5, 1, by: 'input:email')]       // 5 a minute per e-mail address (login forms)
#[Throttle(100, 60, by: 'user')]           // 100 an hour per user; guests per IP
#[Throttle(30, 1, by: 'header:X-Api-Key')]
#[Throttle(10, 1, by: 'route:team')]       // per {team}
```

`input:` values count case-insensitively and trimmed, so `Reader@Example.com` shares the counter of `reader@example.com`.

Repeat it to apply several limits; a request must pass them all, and each keeps its own counter:

```php
#[Throttle(5, 1, by: 'ip')]
#[Throttle(3, 60, by: 'input:email')]      // and 3 an hour per address (newsletter sign-up)
```

`by` is `ip`, `user`, `session`, `header:Name`, `input:field` or `route:parameter`. Any other value fails when routes are discovered, not on the first request. Each route keeps its own counter, and a request without the header, field or parameter is counted by IP, so leaving the field out does not escape the limit or share one bucket with everyone. One built-in limiter reads the limit from the route action, so it works with `route:cache`.

## Webhooks

```php
#[Post('/hooks/github'), Webhook(secret: 'services.github.webhook_secret', header: 'X-Hub-Signature-256', prefix: 'sha256=')]
#[Post('/hooks/mail'), Webhook(secret: 'services.mail.webhook_secret')]      // X-Signature, sha256, hex
#[Post('/hooks/shop'), Webhook(secret: 'services.shop.secret', header: 'X-Shop-Hmac', base64: true)]
#[Post('/hooks/stripe'), Webhook(secret: 'services.stripe.webhook_secret', format: 'stripe')]   // Stripe-Signature: t=…,v1=…
#[Post('/hooks/slack'), Webhook(secret: 'services.slack.signing_secret', header: 'X-Slack-Signature', prefix: 'v0=',
                                timestampHeader: 'X-Slack-Request-Timestamp', payload: 'v0:{timestamp}:{body}')]
#[Post('/hooks/csp'), Webhook]                                               // no signature: stateless and CSRF-free only
```

The signature is an HMAC of the signed payload (the raw body by default, or `payload` with `{body}` and `{timestamp}`), compared in constant time. `secret` is a config key, so the secret stays in `.env`; an unset secret throws rather than letting requests through.

**Replay protection.** When the request carries a signed timestamp (`timestampHeader`, or `format: 'stripe'`), requests more than `tolerance` seconds old (default 300) are refused, and with `once` (default) each signature is accepted only once inside that window, tracked in the cache. Without a timestamp the body signature is still checked, but nothing stops a captured request from being replayed, so prefer a timestamped scheme when the provider offers one.

A missing, wrong, expired or replayed signature is a **403**. A bad `format` or `algorithm` fails when routes are discovered.

## Model binding

```php
#[Bind('post', 'slug')]                                // {post} binds by slug, without {post:slug} in each URI
#[Bind(['post' => 'slug', 'author' => 'username'])]    // routes without the parameter ignore it
```

Bindings for every route go in config instead of `Route::model()` / `Route::bind()`:

```php
'bindings' => [
    'author' => User::class,                       // Route::model
    'post' => [PostResolver::class, 'resolve'],    // built by the container; gets $value and $route
],
```

## Redirects and the fallback

```php
#[Get('/', name: 'landing')]
#[RedirectFrom('/welcome')]                              // 301
final class LandingController {}

#[Get('/stories/{slug}', name: 'stories.show')]
#[RedirectFrom('/posts/{slug}', status: 302)]            // {slug} carries over
```

Redirect routes answer every method (like `Route::redirect`), get only the directory's middleware, and share the target's constraints for parameters with the same name.

```php
#[Fallback]
final class NotFoundController
{
    public function __invoke(): never { abort(404); }
}
```

Unlike the router's own 404, the fallback runs the route middleware, so the error page knows who is signed in.

## Pages and redirects without a controller

```php
// config/routing.php
'views' => [
    'terms' => 'pages.terms',
    'about' => ['view' => 'pages.about', 'name' => 'about', 'data' => ['team' => 'Newsroom']],
],
'redirects' => [
    '/old-terms' => '/terms',                                          // 301
    '/promo' => ['to' => 'https://shop.example.com', 'status' => 302],
],
```

Both take `middleware` (default: `static_middleware`, i.e. `['web']`), `name`, `domain` and `where`, and are ordered, cached, listed and linted with the attribute routes.

## Validation

```php
#[Get('/search', name: 'search')]
#[Validate(['q' => 'required|string|max:120', 'page' => 'integer|min:1'], messages: ['q.max' => 'Shorter, please.'])]
public function search(Request $request)
{
    $request->routeValidated('q');     // the validated data
    $request->routeValidated();        // all of it
}

#[Validate(StorePostRequest::class)]   // a FormRequest (authorize() runs too)
#[Validate($rules, errorBag: 'search')]
```

Failures behave like Laravel's: a redirect back with errors, or 422 JSON.

Route **parameters** get full rules, checked on the raw value before model binding:

```php
#[Get('/archive/{year}', name: 'archive')]
#[ValidateParameter('year', 'integer|between:1990,2100')]           // 404 when it fails
#[ValidateParameter('date', 'date_format:Y-m-d', status: 400)]
```

## Deprecation

```php
#[Deprecated(since: '2026-10-01', sunset: '2027-01-01', successor: 'api.v2.posts')]
```

Responses carry `Deprecation: @1790812800` ([RFC 9745](https://www.rfc-editor.org/rfc/rfc9745)), `Sunset: Fri, 01 Jan 2027 00:00:00 GMT` ([RFC 8594](https://www.rfc-editor.org/rfc/rfc8594)) and `Link: <…>; rel="successor-version"` (a route name or a URL). With `gone: true` the route answers **410** once the sunset has passed.

## Conditions

```php
#[Environment('local')]                       // /dev/mail exists only locally
#[WhenConfig('features.sports')]              // truthy
#[WhenConfig('mail.driver', 'postmark')]      // equal
#[When([Features::class, 'enabled'])]         // a static method, called through the container
```

Conditions are checked when routes register; elsewhere the address is a plain 404.

## Missing models

```php
#[Missing(redirect: 'sports.teams')]          // a named route
#[Missing(handler: 'teamNotFound')]           // a public static method of the controller

public static function teamNotFound(Request $request, ModelNotFoundException $exception) { /* … */ }
```

## Custom attributes

Anything implementing `RouteModifier` is a route option, on classes or methods:

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Staff implements RouteModifier
{
    public function modify(RouteDefinition $route): void
    {
        $route->middleware(['auth', 'verified', 'can:access-newsroom'])
              ->meta('area', 'newsroom');      // readable later: $route->getAction('routing')['meta']
    }
}
```

`RouteDefinition` has `prefix()`, `as()`, `name()`, `middleware()`, `withoutMiddleware()`, `where()`, `defaults()`, `domain()`, `priority()`, `scopeBindings()`, `withTrashed()`, `bind()`, `condition()` and `meta()`. Implement `DeclaresRoutes` to declare routes of your own (that is how `Resource` and `Fallback` work).

## Discovery

Classes are found by reading each file's namespace and class name, so any folder layout works. Directories accept `patterns` (file globs, default `*.php`) and `exclude` (globs relative to the directory). Abstract classes and classes marked `#[NoRoutes]` are skipped.

From a service provider's `register()`:

```php
use Atannex\Routing\Facades\Routing;

Routing::directory(base_path('modules/Billing/Http'), ['prefix' => 'billing', 'middleware' => ['web', 'auth']]);
Routing::controller(HealthController::class, ['prefix' => 'ops']);
```

`Routing::routes()` returns the prepared definitions in matching order; `Routing::register()` registers them yourself when `register` is `false`.

**Caching.** `php artisan routing:cache` writes the discovered routes, `#[Schedule]` tasks and `#[Channel]` classes to `bootstrap/cache/routing.php` (atomically, so a request never reads half a file), so requests and `schedule:run` skip reflection; `php artisan optimize` runs it. With `route:cache` the routes are in Laravel's cache and nothing is discovered at all. As with any cache, clear it (`routing:clear`, `optimize:clear`) to see new attributes.

## Scheduling

`#[Schedule]` on a command, job, invokable class or public method adds it to Laravel's scheduler, next to anything still in `routes/console.php`; `php artisan schedule:list` shows both.

```php
use Atannex\Routing\Attributes\Schedule;

#[Schedule('dailyAt:06:00', parameters: ['--fresh' => true], withoutOverlapping: true, onOneServer: true)]
#[Schedule('0 18 * * 1-5', environments: ['production'])]
final class SendNewsletterCommand extends Command { /* … */ }

#[Schedule('hourly', queue: 'feeds')]
final class SyncFeedsJob implements ShouldQueue { /* … */ }

final class Housekeeping                     // what used to be closures in routes/console.php
{
    #[Schedule('daily')]  public function pruneDrafts(Drafts $drafts) { /* … */ }
    #[Schedule('hourly')] public function warmCaches() { /* … */ }
}

// Commands from other packages, by name, from any class in the directories:
#[Schedule('dailyAt:04:20', command: 'queue:prune-failed', parameters: ['--hours' => 720], onOneServer: true)]
#[Schedule('dailyAt:{newsletters.morning_at=06:00}', command: 'newsletters:send', parameters: ['morning'])]
#[Schedule('everyMinute', command: 'live:refresh', whenConfig: 'services.youtube.channel_id')]
final class Maintenance {}
```

- **Frequency**: a scheduler method with arguments after a colon (`daily`, `hourlyAt:15`, `twiceDaily:1,13`, `weeklyOn:1,8:00`) or a cron expression. An unknown name throws when the schedule is built.
- **Config in the frequency**: `{key}` or `{key=default}` in the arguments reads config when the schedule is built, e.g. `weeklyOn:{mail.weekly_day=0},{mail.weekly_at=07:00}`.
- **What runs**: `command: 'name'` runs that command; otherwise a command as a command (`parameters` are its arguments and options); a method or `__invoke` class through the container; anything else dispatched as a job, on `queue`/`connection`.
- **Options**: `whenConfig` (run only while that config value is filled), `timezone`, `withoutOverlapping` (`true` or minutes), `onOneServer`, `runInBackground`, `evenInMaintenanceMode`, `environments`, `description`. Repeat the attribute for several schedules.

Classes are found in `schedule.directories` (`app/Console/Commands`, `app/Jobs`). `ROUTING_SCHEDULE=false` turns it off. Don't also schedule the same class in `routes/console.php`, or it runs twice. Alias one import if the file also uses the `Schedule` facade.

## Broadcast channels

```php
use Atannex\Routing\Attributes\Channel;

#[Channel('orders.{order}')]
#[Channel('newsroom', guards: ['web', 'admin'])]
final class OrderChannel
{
    public function join(User $user, Order $order): bool { return $user->id === $order->user_id; }
}
```

Replaces `Broadcast::channel()` in `routes/channels.php`. Classes are found in `channels.directories` (`app/Broadcasting`), and registered only when the broadcaster is first used. `ROUTING_CHANNELS=false` turns it off.

## Console

| Command                                                     |                                                                                                                                                                                                                                                                                                                                                                                                          |
| ----------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `routing:list [--path=] [--name=] [--controller=] [--json]` | Attribute routes in matching order, with priority and the `file:line` they are declared at.                                                                                                                                                                                                                                                                                                              |
| `routing:lint [--strict]`                                   | Errors: an address or name declared twice, unknown middleware, a redirect that cannot fill the target's parameters, missing handlers or FormRequest classes. Warnings: a route an earlier one makes unreachable (checked across every route, packages included), constraints or parameter rules for parameters the URI lacks, session-guard `auth`/`guest` or `password.confirm` on a `#[Stateless]` or `#[Webhook]` route (nobody could be signed in). Exits non-zero on errors (and warnings with `--strict`), so it fits in CI. |
| `routing:convert [--path=] [--base=web]`                    | Prints the attributes for every controller route still in a route file, grouped by controller; redirects as `#[RedirectFrom]`; closure routes listed apart.                                                                                                                                                                                                                                              |
| `routing:cache`, `routing:clear`                            | The discovery cache.                                                                                                                                                                                                                                                                                                                                                                                     |

`php artisan about` shows the registration mode and cache state.

Laravel registers commands from `app/Console/Commands` itself; list other folders (modules, `src/Console`) in `commands` and theirs are registered too.

## Configuration reference

| Key           | Default                                                                     |                                                                                                                                                                                                             |
| ------------- | --------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `register`    | `'booted'`                                                                  | `'booted'` (after the app has booted), `'boot'` (while this package boots, ahead of later routes), or `false` (call `Routing::register()`).                                                                 |
| `directories` | `['app/Http/Controllers' => ['middleware' => ['web']]]`                     | Paths with group options: `prefix`, `as`, `middleware`, `withoutMiddleware`, `domain`, `where`, `defaults`, `scopeBindings`, `priority`, plus `patterns` and `exclude`.                                     |
| `patterns`    | `id`, `slug`, `slug-path`, `year`, `month`, `day`, `uuid`, `ulid`, `locale` | Named patterns for `@name`; a value may be a `[class, method]` callable.                                                                                                                                    |
| `parameters`  | `[]`                                                                        | Constraint per parameter name for routes without their own.                                                                                                                                                 |
| `naming`      | `null`                                                                      | `'uri'` names unnamed routes from their path (`sports/leagues/{league}/table` ⇒ `sports.leagues.table`, `.store`/`.update`/`.destroy` for other verbs), or a `[class, method]` callable receiving `$route`. |
| `ordering`    | `'specificity'`                                                             | or `'declaration'`.                                                                                                                                                                                         |
| `cache`       | `bootstrap/cache/routing.php`                                               | Discovery cache file.                                                                                                                                                                                       |
| `views`, `redirects` | `[]`                                                                 | Routes without a controller ([details](#pages-and-redirects-without-a-controller)).                                                                                                                        |
| `static_middleware` | `['web']`                                                             | Middleware for `views` and `redirects`.                                                                                                                                                                     |
| `bindings`    | `[]`                                                                        | Global bindings: a model class, or a `[class, method]` resolver.                                                                                                                                           |
| `schedule`    | enabled; `app/Console/Commands`, `app/Jobs`                                 | Where `#[Schedule]` classes are found.                                                                                                                                                                      |
| `channels`    | enabled; `app/Broadcasting`                                                 | Where `#[Channel]` classes are found.                                                                                                                                                                       |
| `commands`    | `[]`                                                                        | Extra folders whose console commands are registered.                                                                                                                                                       |

## Attribute reference

| Declares routes                                             | Groups and options                                                                                                    | Middleware                                                                                         | Constraints                                                                              | Behaviour                                                                                                         |
| ----------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `Get` `Post` `Put` `Patch` `Delete` `Options` `Any` `Route` | `Group` `Prefix` `Name` `Middleware` `WithoutMiddleware` `Domain` `Defaults` `Priority` `ScopeBindings` `WithTrashed` | `Auth` `Guest` `Verified` `Signed` `Throttle` `Can` `PasswordConfirm` `CacheHeaders` `WithoutCsrf` `Json` `Stateless` `Webhook` `Precognitive` | `Where` `WhereNumber` `WhereAlpha` `WhereAlphaNumeric` `WhereUuid` `WhereUlid` `WhereIn` | `Validate` `ValidateParameter` `Deprecated` `Missing` `RedirectFrom` `Environment` `WhenConfig` `When` `NoRoutes` `Bind` `Block` |
| `Resource` `ApiResource` `Singleton` `Fallback`; outside HTTP: `Schedule` `Channel` |                                                                                                                       |                                                                                                    |                                                                                          |                                                                                                                   |

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT. See [LICENSE](LICENSE).
