<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers;

use Atannex\Routing\Attributes\Fallback;
use Illuminate\Http\Response;

#[Fallback]
final class NotFoundController
{
    public function __invoke(): Response
    {
        return new Response('fixture fallback', 404);
    }
}
