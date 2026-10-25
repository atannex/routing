<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;
use InvalidArgumentException;

/**
 * A webhook endpoint: no session, cookies or CSRF token, and, with a
 * secret, the request must carry a valid HMAC signature (403 otherwise):
 *
 *     #[Webhook(secret: 'services.github.webhook_secret', header: 'X-Hub-Signature-256', prefix: 'sha256=')]
 *     #[Webhook(secret: 'services.mail.webhook_secret')]                  // X-Signature, sha256, hex
 *     #[Webhook(secret: 'services.stripe.webhook_secret', format: 'stripe')]
 *     #[Webhook(secret: 'services.slack.signing_secret', header: 'X-Slack-Signature', prefix: 'v0=',
 *               timestampHeader: 'X-Slack-Request-Timestamp', payload: 'v0:{timestamp}:{body}')]
 *
 * With a timestamp (a timestamp header, or the Stripe format), requests
 * older than `tolerance` seconds are refused, and with `once` each
 * signature is accepted a single time, so a captured request cannot be
 * replayed. `secret` is a config key, so the secret itself stays in .env.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Webhook implements RouteModifier
{
    public function __construct(
        public ?string $secret = null,
        public ?string $header = null,
        public string $algorithm = 'sha256',
        public string $prefix = '',
        public bool $base64 = false,
        public string $format = 'hmac',
        public ?string $timestampHeader = null,
        public string $payload = '{body}',
        public int $tolerance = 300,
        public bool $once = true,
    ) {
        if (! in_array($format, ['hmac', 'stripe'], true)) {
            throw new InvalidArgumentException("Unknown webhook format \"{$format}\". Use hmac or stripe.");
        }

        if (! in_array($algorithm, hash_hmac_algos(), true)) {
            throw new InvalidArgumentException("Unknown HMAC algorithm \"{$algorithm}\".");
        }
    }

    public function modify(RouteDefinition $route): void
    {
        $route->withoutMiddleware(Stateless::MIDDLEWARE);

        if ($this->secret !== null) {
            $route->webhook = [
                'secret' => $this->secret,
                'header' => $this->header ?? ($this->format === 'stripe' ? 'Stripe-Signature' : 'X-Signature'),
                'algorithm' => $this->algorithm,
                'prefix' => $this->prefix,
                'base64' => $this->base64,
                'format' => $this->format,
                'timestamp' => $this->timestampHeader,
                'payload' => $this->payload,
                'tolerance' => $this->tolerance,
                'once' => $this->once,
            ];
        }
    }
}
