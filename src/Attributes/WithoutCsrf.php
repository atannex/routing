<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Atannex\Routing\Contracts\RouteModifier;
use Atannex\Routing\Definition\RouteDefinition;
use Attribute;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * Accepts requests without a CSRF token: webhooks, CSP reports, mail apps'
 * one-click unsubscribe. Protect the route another way (a signature, a secret).
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class WithoutCsrf implements RouteModifier
{
    /** Laravel 13 checks CSRF with PreventRequestForgery; earlier versions with ValidateCsrfToken. */
    public const MIDDLEWARE = [
        ValidateCsrfToken::class,
        'Illuminate\\Foundation\\Http\\Middleware\\PreventRequestForgery',
    ];

    public function modify(RouteDefinition $route): void
    {
        $route->withoutMiddleware(self::MIDDLEWARE);
    }
}
