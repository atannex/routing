<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Elsewhere;

use Atannex\Routing\Attributes\Get;
use Atannex\Routing\Attributes\Middleware;
use Atannex\Routing\Attributes\RedirectFrom;

/** Every mistake routing:lint should catch. */
final class BrokenController
{
    #[Get('/about', name: 'about-again')]
    public function duplicate(): void {}

    #[Get('/ok', name: 'about')]
    #[Middleware('no-such-middleware')]
    public function unknownMiddleware(): void {}

    #[Get('/things/{thing}', name: 'things.show')]
    #[RedirectFrom('/stuff')]
    public function lostParameter(): void {}
}
