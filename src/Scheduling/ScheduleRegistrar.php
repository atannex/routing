<?php

declare(strict_types=1);

namespace Atannex\Routing\Scheduling;

use Atannex\Routing\Attributes\Schedule as Scheduled;
use Atannex\Routing\Discovery\ClassFinder;
use Atannex\Routing\Exceptions\RoutingException;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;

/**
 * Adds the classes carrying #[Schedule] to Laravel's scheduler: commands
 * as command events, invokable classes as callbacks, anything else as a job.
 */
final class ScheduleRegistrar
{
    /**
     * @param  list<string>  $directories  absolute paths
     * @param  list<array{0: class-string, 1: string|null, 2: array<string, mixed>}>|null  $cached  from the discovery cache
     */
    public function __construct(
        private readonly array $directories,
        private readonly ?array $cached = null,
    ) {}

    /**
     * @return list<Event>
     */
    public function register(Schedule $schedule): array
    {
        $events = [];

        foreach ($this->tasks() as [$class, $method, $attribute]) {
            $events[] = $this->add($schedule, $class, $attribute, $method);
        }

        return $events;
    }

    /**
     * Every #[Schedule] found: on classes, then on their public methods.
     *
     * @return list<array{0: class-string, 1: string|null, 2: Scheduled}>
     */
    public function tasks(): array
    {
        if ($this->cached !== null) {
            return array_map(fn (array $task) => [$task[0], $task[1], new Scheduled(...$task[2])], $this->cached);
        }

        $finder = new ClassFinder;
        $tasks = [];

        foreach ($this->directories as $directory) {
            foreach ($finder->find($directory) as $class) {
                $reflection = new ReflectionClass($class);

                if (! $reflection->isInstantiable()) {
                    continue;
                }

                foreach ($reflection->getAttributes(Scheduled::class) as $attribute) {
                    $tasks[] = [$class, null, $attribute->newInstance()];
                }

                foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    if ($method->isStatic() || $method->isConstructor()) {
                        continue;
                    }

                    foreach ($method->getAttributes(Scheduled::class) as $attribute) {
                        $tasks[] = [$class, $method->getName(), $attribute->newInstance()];
                    }
                }
            }
        }

        return $tasks;
    }

    /**
     * The tasks as plain data, for the discovery cache.
     *
     * @return list<array{0: class-string, 1: string|null, 2: array<string, mixed>}>
     */
    public function export(): array
    {
        return array_map(fn (array $task) => [$task[0], $task[1], get_object_vars($task[2])], $this->tasks());
    }

    /**
     * Classes with #[Schedule] on the class itself.
     *
     * @return array<class-string, list<Scheduled>>
     */
    public function classes(): array
    {
        $classes = [];

        foreach ($this->tasks() as [$class, $method, $attribute]) {
            if ($method === null) {
                $classes[$class][] = $attribute;
            }
        }

        return $classes;
    }

    /**
     * @param  class-string  $class
     */
    private function add(Schedule $schedule, string $class, Scheduled $attribute, ?string $method = null): Event
    {
        $event = match (true) {
            $attribute->command !== null => $schedule->command($attribute->command, $attribute->parameters),
            $method !== null => $schedule->call($class.'@'.$method, $attribute->parameters)->name($class.'::'.$method),
            is_subclass_of($class, Command::class) => $schedule->command($class, $attribute->parameters),
            method_exists($class, '__invoke') => $schedule->call($class, $attribute->parameters)->name($class),
            default => $schedule->job($class, $attribute->queue, $attribute->connection),
        };

        $this->frequency($event, $attribute->frequency, $class);

        if ($attribute->timezone !== null) {
            $event->timezone($attribute->timezone);
        }
        if ($attribute->withoutOverlapping !== false) {
            $attribute->withoutOverlapping === true ? $event->withoutOverlapping() : $event->withoutOverlapping($attribute->withoutOverlapping);
        }
        if ($attribute->onOneServer) {
            $event->onOneServer();
        }
        if ($attribute->runInBackground) {
            $event->runInBackground();
        }
        if ($attribute->evenInMaintenanceMode) {
            $event->evenInMaintenanceMode();
        }
        if ($attribute->environments !== []) {
            $event->environments($attribute->environments);
        }
        if ($attribute->whenConfig !== null) {
            $key = $attribute->whenConfig;
            $event->when(fn () => filled(config($key)));
        }
        if ($attribute->description !== null) {
            $event->description($attribute->description);
        }

        return $event;
    }

    /** "daily", "dailyAt:06:00", "twiceDaily:1,13" or a cron expression; {key} and {key=default} read config. */
    private function frequency(Event $event, string $frequency, string $class): void
    {
        $frequency = (string) preg_replace_callback(
            '/\{([A-Za-z0-9_.\-]+)(?:=([^}]*))?\}/',
            fn (array $match) => (string) config($match[1], $match[2] ?? null),
            trim($frequency),
        );

        if (str_contains($frequency, ' ')) {
            $event->cron($frequency);

            return;
        }

        [$method, $arguments] = array_pad(explode(':', $frequency, 2), 2, null);

        if (! method_exists($event, $method) || in_array($method, ['cron', 'timezone'], true)) {
            throw new RoutingException("{$class}: unknown schedule frequency \"{$method}\". Use a frequency method (daily, hourlyAt:15, dailyAt:06:00...) or a cron expression.");
        }

        $event->{$method}(...($arguments === null ? [] : explode(',', $arguments)));
    }
}
