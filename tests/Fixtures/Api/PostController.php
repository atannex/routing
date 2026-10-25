<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Api;

use Atannex\Routing\Attributes\ApiResource;
use Atannex\Routing\Attributes\Post;

#[ApiResource('posts', only: ['index', 'show'])]
final class PostController
{
    public function index(): array
    {
        return ['posts' => []];
    }

    public function show(string $post): array
    {
        return ['post' => $post];
    }

    #[Post('posts/{post}/like', name: 'posts.like')]
    public function like(string $post): array
    {
        return ['liked' => $post];
    }
}
