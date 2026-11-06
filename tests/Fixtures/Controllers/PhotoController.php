<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers;

use Atannex\Routing\Attributes\Middleware;
use Atannex\Routing\Attributes\Resource;

#[Resource('photos')]
final class PhotoController
{
    public function index(): string
    {
        return 'photos';
    }

    #[Middleware('fixture.inner')]
    public function store(): string
    {
        return 'stored';
    }

    public function show(string $photo): string
    {
        return 'photo:'.$photo;
    }

    public function destroy(string $photo): string
    {
        return 'destroyed:'.$photo;
    }
}
