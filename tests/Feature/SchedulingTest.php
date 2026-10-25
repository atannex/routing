<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Feature;

use Atannex\Routing\Exceptions\RoutingException;
use Atannex\Routing\Scheduling\ScheduleRegistrar;
use Atannex\Routing\Tests\Fixtures\Scheduled\Housekeeping;
use Atannex\Routing\Tests\Fixtures\Scheduled\PruneTask;
use Atannex\Routing\Tests\Fixtures\Scheduled\SyncJob;
use Atannex\Routing\Tests\TestCase;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

final class SchedulingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        if (! isset($this->routing['schedule'])) {
            $app['config']->set('routing.schedule.directories', [__DIR__.'/../Fixtures/Scheduled']);
        }
    }

    /** @return array<string, Event> keyed by summary */
    private function events(): array
    {
        $events = [];

        foreach ($this->app->make(Schedule::class)->events() as $event) {
            $events[$event->expression.' '.$event->getSummaryForDisplay()] = $event;
        }

        return $events;
    }

    public function test_commands_are_scheduled_from_their_attributes(): void
    {
        $morning = collect($this->app->make(Schedule::class)->events())
            ->first(fn (Event $event) => $event->expression === '0 6 * * *');

        $this->assertNotNull($morning);
        $this->assertStringContainsString('fixture:report --fresh', $morning->command);
        $this->assertSame('Builds the report', $morning->description);
        $this->assertTrue($morning->withoutOverlapping);
        $this->assertTrue($morning->onOneServer);

        $evening = collect($this->app->make(Schedule::class)->events())
            ->first(fn (Event $event) => $event->expression === '0 18 * * 1-5');

        $this->assertSame(['production'], $evening->environments);
        $this->assertSame('Evening report', $evening->description);
    }

    public function test_invokable_classes_and_jobs_are_scheduled(): void
    {
        $events = $this->events();

        $this->assertArrayHasKey('*/15 * * * * '.PruneTask::class, $events);
        $this->assertSame('Africa/Douala', $events['*/15 * * * * '.PruneTask::class]->timezone);
        $this->assertArrayHasKey('0 * * * * '.SyncJob::class, $events);

        PruneTask::$runs = 0;
        $events['*/15 * * * * '.PruneTask::class]->run($this->app);
        $this->assertSame(1, PruneTask::$runs);
    }

    public function test_classes_without_the_attribute_are_left_alone(): void
    {
        $this->assertCount(5, $this->app->make(Schedule::class)->events());
        $this->assertCount(3, $this->app->make(ScheduleRegistrar::class)->classes());
    }

    public function test_methods_can_be_scheduled(): void
    {
        $events = $this->events();
        $key = '0 8 * * 1 '.Housekeeping::class.'::pruneDrafts';

        $this->assertArrayHasKey($key, $events);

        Housekeeping::$ran = [];
        $events[$key]->run($this->app);
        $this->assertSame(['prune'], Housekeeping::$ran);
    }

    public function test_commands_in_extra_directories_are_registered(): void
    {
        $this->routing = ['commands' => [__DIR__.'/../Fixtures/Scheduled']];
        $this->refreshApplication();

        $this->artisan('fixture:quiet')->assertSuccessful();
    }

    public function test_an_unknown_frequency_is_refused(): void
    {
        $registrar = new ScheduleRegistrar([__DIR__.'/../Fixtures/Scheduled']);
        $attribute = new \Atannex\Routing\Attributes\Schedule('sometimes');

        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('unknown schedule frequency "sometimes"');

        (fn () => $this->add(new Schedule, PruneTask::class, $attribute))->call($registrar);
    }

    public function test_scheduling_can_be_switched_off(): void
    {
        $this->routing = ['schedule' => ['enabled' => false, 'directories' => [__DIR__.'/../Fixtures/Scheduled']]];
        $this->refreshApplication();

        $this->assertCount(0, $this->app->make(Schedule::class)->events());
    }

    public function test_schedule_list_shows_them(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('fixture:report --fresh')
            ->expectsOutputToContain('PruneTask')
            ->expectsOutputToContain('SyncJob')
            ->assertSuccessful();
    }

    public function test_the_schedule_comes_from_the_discovery_cache(): void
    {
        $this->artisan('routing:cache')->assertSuccessful();

        $this->routing = ['schedule' => ['enabled' => true, 'directories' => []]];
        $this->refreshApplication();

        $this->assertCount(5, $this->app->make(Schedule::class)->events());
        $this->artisan('schedule:list')->expectsOutputToContain('fixture:report --fresh')->assertSuccessful();
    }
}
