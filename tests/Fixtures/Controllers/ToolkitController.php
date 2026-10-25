<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers;

use Atannex\Routing\Attributes\Auth;
use Atannex\Routing\Attributes\Bind;
use Atannex\Routing\Attributes\Get;
use Atannex\Routing\Attributes\Json;
use Atannex\Routing\Attributes\Post;
use Atannex\Routing\Attributes\Stateless;
use Atannex\Routing\Attributes\Throttle;
use Atannex\Routing\Attributes\Webhook;

final class ToolkitController
{
    #[Get('toolkit/stories/{story}/{author}', name: 'toolkit.story'), Bind(['story' => 'slug', 'author' => 'username'])]
    public function story(string $story, string $author): string
    {
        return $story.'|'.$author;
    }

    #[Get('toolkit/missing', name: 'toolkit.json'), Json]
    public function missing(): never
    {
        abort(404);
    }

    #[Get('toolkit/feed', name: 'toolkit.feed'), Stateless]
    public function feed(): string
    {
        return 'feed';
    }

    #[Post('toolkit/hook', name: 'toolkit.hook'), Webhook(secret: 'fixtures.hook_secret', header: 'X-Hub-Signature-256', prefix: 'sha256=')]
    public function hook(): string
    {
        return 'received';
    }

    #[Post('toolkit/open-hook', name: 'toolkit.open-hook'), Webhook]
    public function openHook(): string
    {
        return 'open';
    }

    #[Post('toolkit/login', name: 'toolkit.login'), Throttle(2, 1, by: 'input:email'), Stateless]
    public function login(): string
    {
        return 'tried';
    }

    #[Post('toolkit/stripe', name: 'toolkit.stripe'), Webhook(secret: 'fixtures.hook_secret', format: 'stripe')]
    public function stripe(): string
    {
        return 'stripe';
    }

    #[Post('toolkit/slack', name: 'toolkit.slack'), Webhook(secret: 'fixtures.hook_secret', header: 'X-Slack-Signature', prefix: 'v0=', timestampHeader: 'X-Slack-Request-Timestamp', payload: 'v0:{timestamp}:{body}')]
    public function slack(): string
    {
        return 'slack';
    }

    #[Get('toolkit/private-feed', name: 'toolkit.private-feed'), Stateless, Auth]
    public function privateFeed(): string
    {
        return 'private';
    }
}
