<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers;

use Atannex\Routing\Attributes\Block;
use Atannex\Routing\Attributes\Post;
use Atannex\Routing\Attributes\Precognitive;

final class CheckoutController
{
    #[Post('checkout', name: 'checkout'), Block(lockSeconds: 5, waitSeconds: 3), Precognitive]
    public function __invoke(): string
    {
        return 'checked out';
    }
}
