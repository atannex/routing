<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers;

use Atannex\Routing\Attributes\RedirectFrom;
use Atannex\Routing\Attributes\Resource;

#[Resource('magazines.articles', only: ['index', 'store', 'show', 'destroy'], authorize: 'App\Models\Article', scoped: ['article' => 'slug'])]
final class ArticleController
{
    public function index(): string
    {
        return 'articles';
    }

    public function store(): string
    {
        return 'stored';
    }

    #[RedirectFrom('/old/{magazine}/articles/{article}')]
    public function show(): string
    {
        return 'article';
    }

    public function destroy(): string
    {
        return 'deleted';
    }
}
