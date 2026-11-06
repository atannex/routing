<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Channels;

use Atannex\Routing\Attributes\Channel;

#[Channel('orders.{order}')]
#[Channel('newsroom', guards: ['web'])]
final class OrderChannel
{
    public function join(mixed $user, mixed $order = null): bool
    {
        return true;
    }
}
