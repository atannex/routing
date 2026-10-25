<?php

declare(strict_types=1);

namespace Atannex\Routing\Broadcasting;

use Atannex\Routing\Attributes\Channel;
use Atannex\Routing\Discovery\ClassFinder;
use ReflectionClass;

/**
 * Registers the classes carrying #[Channel] with the broadcaster.
 */
final class ChannelRegistrar
{
    /**
     * @param  list<string>  $directories  absolute paths
     * @param  list<array{0: class-string, 1: string, 2: list<string>}>|null  $cached  from the discovery cache
     */
    public function __construct(
        private readonly array $directories,
        private readonly ?array $cached = null,
    ) {}

    /**
     * @param  object  $broadcaster  the BroadcastManager or a Broadcaster
     * @return array<string, class-string> channel name => class
     */
    public function register(object $broadcaster): array
    {
        $registered = [];

        foreach ($this->channels() as [$class, $channel]) {
            $broadcaster->channel($channel->name, $class, $channel->guards === [] ? [] : ['guards' => $channel->guards]);
            $registered[$channel->name] = $class;
        }

        return $registered;
    }

    /**
     * The channels as plain data, for the discovery cache.
     *
     * @return list<array{0: class-string, 1: string, 2: list<string>}>
     */
    public function export(): array
    {
        return array_map(fn (array $channel) => [$channel[0], $channel[1]->name, $channel[1]->guards], $this->channels());
    }

    /**
     * @return list<array{0: class-string, 1: Channel}>
     */
    public function channels(): array
    {
        if ($this->cached !== null) {
            return array_map(fn (array $channel) => [$channel[0], new Channel($channel[1], $channel[2])], $this->cached);
        }

        $finder = new ClassFinder;
        $channels = [];

        foreach ($this->directories as $directory) {
            foreach ($finder->find($directory) as $class) {
                $reflection = new ReflectionClass($class);

                if (! $reflection->isInstantiable()) {
                    continue;
                }

                foreach ($reflection->getAttributes(Channel::class) as $attribute) {
                    $channels[] = [$class, $attribute->newInstance()];
                }
            }
        }

        return $channels;
    }
}
