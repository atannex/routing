<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Feature;

use Atannex\Routing\Attributes\Throttle;
use Atannex\Routing\Attributes\Webhook;
use Atannex\Routing\Facades\Routing;
use Atannex\Routing\Http\Middleware\ForceJson;
use Atannex\Routing\Http\Middleware\VerifyWebhookSignature;
use Atannex\Routing\Tests\Fixtures\AuthorResolver;
use Atannex\Routing\Tests\Fixtures\Channels\OrderChannel;
use Atannex\Routing\Tests\TestCase;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Session\Middleware\StartSession;

final class ToolkitTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('fixtures.hook_secret', 'shh');
        $app['config']->set('routing.views', [
            'terms' => ['view' => 'fixtures::terms', 'name' => 'terms', 'data' => ['version' => 3]],
        ]);
        $app['config']->set('routing.redirects', [
            '/old-terms' => '/terms',
            '/promo' => ['to' => 'https://example.com/sale', 'status' => 302, 'name' => 'promo'],
        ]);
        if (! isset($this->routing['channels'])) {
            $app['config']->set('routing.channels.directories', [__DIR__.'/../Fixtures/Channels']);
        }
        $app['config']->set('broadcasting.default', 'null');
        $app['config']->set('routing.bindings', ['author' => [AuthorResolver::class, 'resolve']]);
        $app['view']->addNamespace('fixtures', __DIR__.'/../Fixtures/views');
    }

    public function test_bind_sets_the_binding_fields(): void
    {
        $route = $this->route('toolkit.story');

        $this->assertSame('slug', $route->bindingFieldFor('story'));
        $this->assertSame('username', $route->bindingFieldFor('author'));
        $this->assertSame('toolkit/stories/{story}/{author}', $route->uri());
    }

    public function test_bindings_from_config_resolve_parameters(): void
    {
        $this->get('/toolkit/stories/first/ada')->assertOk()->assertSee('first|author:ADA');
    }

    public function test_json_renders_errors_as_json(): void
    {
        $this->assertContains(ForceJson::class, $this->route('toolkit.json')->gatherMiddleware());
        $this->get('/toolkit/missing')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }

    public function test_stateless_drops_the_session_and_cookies(): void
    {
        $route = $this->route('toolkit.feed');

        $this->assertNotContains(StartSession::class, $this->app['router']->gatherRouteMiddleware($route));
        $this->get('/toolkit/feed')->assertOk()->assertCookieMissing('laravel_session');
    }

    public function test_webhooks_check_the_signature_and_skip_csrf(): void
    {
        $body = '{"event":"push"}';
        $signature = 'sha256='.hash_hmac('sha256', $body, 'shh');

        $this->call('POST', '/toolkit/hook', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertOk()->assertSee('received');
        $this->call('POST', '/toolkit/hook', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => 'sha256=nope'], $body)->assertForbidden();
        $this->call('POST', '/toolkit/hook', [], [], [], [], $body)->assertForbidden();

        // Without a secret it is only exempt from CSRF and sessions.
        $this->post('/toolkit/open-hook')->assertOk()->assertSee('open');
        $this->assertNotContains(VerifyWebhookSignature::class, $this->route('toolkit.open-hook')->gatherMiddleware());
    }

    public function test_inline_throttle_keys_by_input(): void
    {
        $this->post('/toolkit/login', ['email' => 'a@example.com'])->assertOk();
        $this->post('/toolkit/login', ['email' => 'a@example.com'])->assertOk();
        $this->post('/toolkit/login', ['email' => 'a@example.com'])->assertStatus(429);

        // Another address has its own counter.
        $this->post('/toolkit/login', ['email' => 'b@example.com'])->assertOk();
    }

    public function test_resources_authorize_each_action_and_scope_bindings(): void
    {
        $this->assertContains('can:viewAny,App\Models\Article', $this->route('magazines.articles.index')->gatherMiddleware());
        $this->assertContains('can:create,App\Models\Article', $this->route('magazines.articles.store')->gatherMiddleware());
        $this->assertContains('can:view,article', $this->route('magazines.articles.show')->gatherMiddleware());
        $this->assertContains('can:delete,article', $this->route('magazines.articles.destroy')->gatherMiddleware());
        $this->assertSame('slug', $this->route('magazines.articles.show')->bindingFieldFor('article'));

        // A guest fails the policy check.
        $this->get('/magazines/1/articles')->assertForbidden();

        // A redirect to the resource does not inherit its authorization.
        $redirect = collect($this->app['router']->getRoutes()->getRoutes())->first(fn ($route) => $route->uri() === 'old/{magazine}/articles/{article}');
        $this->assertNotNull($redirect);
        $this->assertSame([], array_filter($redirect->gatherMiddleware(), fn ($m) => str_starts_with((string) $m, 'can:')));
    }

    public function test_views_and_redirects_from_config(): void
    {
        $this->get('/terms')->assertOk()->assertSee('Terms v3');
        $this->assertSame('terms', $this->route('terms')->uri());

        $this->get('/old-terms')->assertRedirect('/terms')->assertStatus(301);
        $this->get('/promo')->assertRedirect('https://example.com/sale')->assertStatus(302);
    }

    public function test_channels_are_registered(): void
    {
        $channels = $this->app->make(BroadcastManager::class)->driver()->getChannels();

        $this->assertSame(OrderChannel::class, $channels['orders.{order}']);
        $this->assertSame(OrderChannel::class, $channels['newsroom']);
    }

    public function test_everything_survives_the_discovery_cache(): void
    {
        $this->artisan('routing:cache')->assertSuccessful();
        $this->refreshApplication();
        $this->assertTrue(Routing::isCached());

        $this->assertSame('slug', $this->route('toolkit.story')->bindingFieldFor('story'));
        $this->assertContains('can:view,article', $this->route('magazines.articles.show')->gatherMiddleware());
        $this->get('/terms')->assertOk()->assertSee('Terms v3');
        $this->get('/old-terms')->assertRedirect('/terms');

        $this->post('/toolkit/login', ['email' => 'c@example.com'])->assertOk();
        $this->post('/toolkit/login', ['email' => 'c@example.com'])->assertOk();
        $this->post('/toolkit/login', ['email' => 'c@example.com'])->assertStatus(429);
    }

    public function test_requests_without_the_throttle_field_are_counted_by_ip(): void
    {
        $this->post('/toolkit/login')->assertOk();
        $this->post('/toolkit/login')->assertOk();
        $this->post('/toolkit/login')->assertStatus(429);

        // Another address is not caught in the same bucket.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->post('/toolkit/login')->assertOk();
    }

    public function test_a_bad_throttle_key_fails_at_discovery(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown throttle key "email"');

        new Throttle(5, 1, by: 'email');
    }

    public function test_a_named_limiter_cannot_take_a_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Throttle('uploads', by: 'user');
    }

    public function test_stripe_signatures_are_checked_with_their_timestamp(): void
    {
        $body = '{"type":"charge.succeeded"}';
        $sign = fn (int $t) => 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$body, 'shh');
        $post = fn (string $header) => $this->call('POST', '/toolkit/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $header], $body);

        $header = $sign(time());
        $post($header)->assertOk()->assertSee('stripe');
        $post($header)->assertForbidden();                         // replayed
        $post($sign(time() - 600))->assertForbidden();             // too old
        $post('t='.time().',v1=deadbeef')->assertForbidden();      // wrong
        $post('v1='.hash_hmac('sha256', $body, 'shh'))->assertForbidden(); // no timestamp
    }

    public function test_timestamped_signatures_in_a_custom_payload(): void
    {
        $body = 'token=x&team_id=T1';
        $t = (string) time();
        $signature = 'v0='.hash_hmac('sha256', "v0:{$t}:{$body}", 'shh');

        $this->call('POST', '/toolkit/slack', [], [], [], [
            'HTTP_X_SLACK_SIGNATURE' => $signature,
            'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $t,
        ], $body)->assertOk()->assertSee('slack');

        $this->call('POST', '/toolkit/slack', [], [], [], ['HTTP_X_SLACK_SIGNATURE' => $signature], $body)->assertForbidden();
    }

    public function test_webhook_options_are_checked_at_discovery(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Webhook(secret: 'x', format: 'paypal');
    }

    public function test_lint_warns_about_session_auth_without_a_session(): void
    {
        $this->artisan('routing:lint')
            ->expectsOutputToContain('nobody can be signed in')
            ->assertSuccessful();
    }

    public function test_channels_come_from_the_discovery_cache(): void
    {
        $this->artisan('routing:cache')->assertSuccessful();

        // Point discovery at nothing: the channels must come from the cache.
        $this->routing = ['channels' => ['enabled' => true, 'directories' => []]];
        $this->refreshApplication();

        $channels = $this->app->make(BroadcastManager::class)->driver()->getChannels();
        $this->assertSame(OrderChannel::class, $channels['orders.{order}']);
    }
}
