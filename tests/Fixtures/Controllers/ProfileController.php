<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers;

use Atannex\Routing\Attributes\ApiResource;
use Atannex\Routing\Attributes\Singleton;

#[Singleton('profile')]
#[ApiResource('users.comments', only: ['index', 'show'])]
final class ProfileController
{
    public function show(): string
    {
        return 'profile';
    }

    public function edit(): string
    {
        return 'edit';
    }

    public function update(): string
    {
        return 'updated';
    }

    public function index(): string
    {
        return 'comments';
    }
}
