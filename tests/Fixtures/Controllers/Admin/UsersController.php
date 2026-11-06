<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers\Admin;

use Atannex\Routing\Attributes\Get;
use Atannex\Routing\Attributes\Prefix;
use Atannex\Routing\Tests\Fixtures\Attributes\Staff;

#[Prefix('people')]
#[Staff]
final class UsersController extends AdminController
{
    #[Get('/users', name: 'users')]
    public function index(): string
    {
        return 'users';
    }
}
