<?php

declare(strict_types=1);

namespace Atannex\Routing\Http\Middleware;

use Atannex\Routing\Exceptions\RoutingException;
use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the signature declared by #[Webhook(secret: ...)], in constant
 * time. A missing or wrong signature, an expired timestamp or a replayed
 * request is a 403.
 */
final class VerifyWebhookSignature
{
    public function __construct(
        private readonly Config $config,
        private readonly Cache $cache,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $spec = (array) ($route?->getAction('routing')['webhook'] ?? []);
        $secret = $this->config->get($spec['secret']);

        if (! is_string($secret) || $secret === '') {
            throw new RoutingException("Webhook secret [{$spec['secret']}] is not configured.");
        }

        $header = (string) $request->headers->get($spec['header'], '');
        abort_if($header === '', 403, 'Missing signature.');

        $stripe = ($spec['format'] ?? 'hmac') === 'stripe';
        $timed = $stripe || isset($spec['timestamp']);

        [$timestamp, $signatures] = $stripe
            ? $this->stripe($header)
            : [$timed ? $request->headers->get($spec['timestamp']) : null, [$header]];

        if ($timed) {
            abort_unless(is_string($timestamp) && ctype_digit($timestamp), 403, 'Missing signature timestamp.');

            $tolerance = (int) ($spec['tolerance'] ?? 300);
            abort_if($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance, 403, 'Signature expired.');
        }

        $payload = $stripe
            ? $timestamp.'.'.$request->getContent()
            : strtr((string) ($spec['payload'] ?? '{body}'), ['{body}' => $request->getContent(), '{timestamp}' => (string) $timestamp]);

        $digest = hash_hmac($spec['algorithm'], $payload, $secret, (bool) $spec['base64']);
        $expected = $spec['prefix'].($spec['base64'] ? base64_encode($digest) : $digest);

        $valid = false;
        foreach ($signatures as $signature) {
            $valid = hash_equals($expected, $signature) || $valid;
        }
        abort_unless($valid, 403, 'Invalid signature.');

        // A signed timestamp bounds the window; within it, accept each signature once.
        if ($timed && ($spec['once'] ?? true)) {
            $key = 'routing:webhook:'.sha1(($route->getName() ?? $route->uri()).'|'.$expected);
            $ttl = max(1, (int) ($spec['tolerance'] ?? 300) ?: 86400);

            abort_unless($this->cache->add($key, true, $ttl), 403, 'Replayed request.');
        }

        return $next($request);
    }

    /**
     * Stripe-Signature: t=1700000000,v1=hex,v1=hex
     *
     * @return array{0: string|null, 1: list<string>}
     */
    private function stripe(string $header): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        return [$timestamp, $signatures];
    }
}
