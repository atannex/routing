<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Attribute;

/**
 * Schedules a command, job or invokable class from the class itself,
 * instead of in routes/console.php:
 *
 *     #[Schedule('daily')]
 *     #[Schedule('dailyAt:06:00')]                    // a frequency method and its arguments
 *     #[Schedule('twiceDaily:1,13')]
 *     #[Schedule('0 6 * * 1-5')]                      // a cron expression
 *     #[Schedule('hourly', parameters: ['--force' => true], withoutOverlapping: true, onOneServer: true)]
 *
 * On a public method it schedules that method, so one class can hold
 * several small tasks (what used to be closures in routes/console.php):
 *
 *     final class Housekeeping
 *     {
 *         #[Schedule('daily')]  public function pruneDrafts(Drafts $drafts) { … }
 *         #[Schedule('hourly')] public function warmCaches() { … }
 *     }
 *
 * Repeat it to run the class on several schedules. Classes are found in
 * the routing.schedule directories.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Schedule
{
    /**
     * @param  array<int|string, mixed>  $parameters  command arguments and options, or invokable parameters
     * @param  list<string>  $environments
     */
    public function __construct(
        public string $frequency = 'daily',
        public array $parameters = [],
        public ?string $timezone = null,
        public bool|int $withoutOverlapping = false,
        public bool $onOneServer = false,
        public bool $runInBackground = false,
        public bool $evenInMaintenanceMode = false,
        public array $environments = [],
        public ?string $description = null,
        public ?string $queue = null,
        public ?string $connection = null,
    ) {}
}
