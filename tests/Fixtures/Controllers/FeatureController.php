<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers;

use Atannex\Routing\Attributes\Deprecated;
use Atannex\Routing\Attributes\Domain;
use Atannex\Routing\Attributes\Get;
use Atannex\Routing\Attributes\Missing;
use Atannex\Routing\Attributes\Post;
use Atannex\Routing\Attributes\Priority;
use Atannex\Routing\Attributes\Validate;
use Atannex\Routing\Attributes\ValidateParameter;
use Atannex\Routing\Attributes\WithoutCsrf;
use Illuminate\Http\Request;

final class FeatureController
{
    #[Get('/search', name: 'search')]
    #[Validate(['q' => 'required|string|max:5'], messages: ['q.max' => 'Too long.'])]
    public function search(Request $request): array
    {
        return ['q' => $request->routeValidated('q'), 'all' => $request->routeValidated()];
    }

    #[Get('/archive/{year}', name: 'archive')]
    #[ValidateParameter('year', 'integer|between:1990,2100')]
    public function archive(string $year): string
    {
        return 'archive:'.$year;
    }

    #[Get('/legacy', name: 'legacy')]
    #[Deprecated(since: '2026-01-01', sunset: '2099-01-01', successor: 'search')]
    public function legacy(): string
    {
        return 'legacy';
    }

    #[Get('/gone', name: 'gone')]
    #[Deprecated(sunset: '2000-01-01', gone: true)]
    public function gone(): string
    {
        return 'gone';
    }

    #[Get('/clubs/{club}', name: 'clubs.show')]
    #[Missing(redirect: 'about')]
    public function club(string $club): string
    {
        return 'club:'.$club;
    }

    #[Get('/status', name: 'api.status')]
    #[Domain('api.example.test')]
    public function apiStatus(): string
    {
        return 'api';
    }

    #[Post('/hooks', name: 'hooks')]
    #[WithoutCsrf]
    public function hooks(): string
    {
        return 'hooked';
    }

    #[Get('/{anything}', name: 'last')]
    #[Priority(-10)]
    public function last(): string
    {
        return 'last';
    }
}
