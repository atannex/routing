<?php

declare(strict_types=1);

namespace Atannex\Routing\Http\Middleware;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the deprecation headers declared by #[Deprecated]:
 *
 *   Deprecation: @1790812800                       (RFC 9745; "true" without a date)
 *   Sunset: Fri, 01 Jan 2027 00:00:00 GMT          (RFC 8594)
 *   Link: <https://…/v2/posts>; rel="successor-version"
 *
 * With `gone`, the route answers 410 once the sunset has passed.
 */
final class DeprecatedRoute
{
    public function __construct(
        private readonly Router $router,
        private readonly UrlGenerator $url,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $config = (array) ($request->route()?->getAction('routing')['deprecated'] ?? []);
        $sunset = $this->date($config['sunset'] ?? null);

        if (! empty($config['gone']) && $sunset !== null && $sunset <= new DateTimeImmutable) {
            abort(410);
        }

        $response = $next($request);

        $since = $this->date($config['since'] ?? null);
        $response->headers->set('Deprecation', $since ? '@'.$since->getTimestamp() : 'true');

        if ($sunset !== null) {
            $response->headers->set('Sunset', $sunset->setTimezone(new DateTimeZone('UTC'))->format('D, d M Y H:i:s').' GMT');
        }

        if (! empty($config['successor'])) {
            $successor = (string) $config['successor'];
            $link = $this->router->has($successor) ? $this->url->route($successor) : $this->url->to($successor);
            $response->headers->set('Link', '<'.$link.'>; rel="successor-version"', false);
        }

        return $response;
    }

    private function date(?string $value): ?DateTimeImmutable
    {
        return $value ? new DateTimeImmutable($value, new DateTimeZone('UTC')) : null;
    }
}
