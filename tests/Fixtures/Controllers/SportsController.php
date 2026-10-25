<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers;

use Atannex\Routing\Attributes\Get;
use Atannex\Routing\Attributes\Middleware;
use Atannex\Routing\Attributes\Name;
use Atannex\Routing\Attributes\Post;
use Atannex\Routing\Attributes\Prefix;
use Atannex\Routing\Attributes\Throttle;
use Atannex\Routing\Attributes\WhereNumber;

#[Prefix('sports')]
#[Name('sports.')]
#[Middleware('fixture.outer')]
final class SportsController
{
    #[Get('/', name: 'home', defaults: ['page' => 'home'])]
    #[Get('/news', name: 'news', defaults: ['page' => 'news'])]
    public function index(string $page): string
    {
        return 'sports:'.$page;
    }

    #[Get('/matches/{match}', name: 'matches.show')]
    #[WhereNumber('match')]
    #[Middleware('fixture.inner')]
    public function match(string $match): string
    {
        return 'match:'.$match;
    }

    #[Post('/predict/{match}', name: 'predict', middleware: 'fixture.route')]
    #[Throttle(30, 1)]
    public function predict(string $match): string
    {
        return 'predicted:'.$match;
    }

    public function helper(): void {}
}
