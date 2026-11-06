<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers;

use Atannex\Routing\Attributes\Get;
use Atannex\Routing\Attributes\RedirectFrom;
use Atannex\Routing\Attributes\Where;

final class LandingController
{
    #[Get('/', name: 'landing')]
    #[RedirectFrom('/welcome')]
    public function landing(): string
    {
        return 'landing';
    }

    #[Get('/stories/{slug}', name: 'stories.show')]
    #[Where('slug', '[a-z\-]+')]
    #[RedirectFrom('/old-stories/{slug}', status: 302)]
    public function story(string $slug): string
    {
        return 'story:'.$slug;
    }
}
