<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Attribute;

/**
 * Declares a private or presence broadcast channel on its channel class,
 * instead of in routes/channels.php. The class's join() method authorizes
 * the user, as with Broadcast::channel('orders.{order}', OrderChannel::class):
 *
 *     #[Channel('orders.{order}')]
 *     final class OrderChannel
 *     {
 *         public function join(User $user, Order $order): bool { return $user->id === $order->user_id; }
 *     }
 *
 *     #[Channel('newsroom', guards: ['web', 'admin'])]
 *
 * Repeat it for several channel names. Classes are found in the
 * routing.channels directories.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Channel
{
    /**
     * @param  list<string>  $guards
     */
    public function __construct(
        public string $name,
        public array $guards = [],
    ) {}
}
