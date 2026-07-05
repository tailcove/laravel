<?php

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Scheduling\Event as SchedulingEvent;
use Illuminate\Console\Scheduling\EventMutex;
use Tailcove\Laravel\Listeners\LogScheduleTaskFailure;

beforeEach(function () {
    config(['logging.channels.tailcove' => [
        'driver' => 'tailcove',
        'level' => 'debug',
    ]]);
    config([
        'tailcove.endpoint' => $this->endpoint,
        'tailcove.token' => null,
        'tailcove.batch_size' => 1,
    ]);
});

it('logs scheduled task failures', function () {
    $mutex = new class implements EventMutex
    {
        public function create($event)
        {
            return true;
        }

        public function exists($event)
        {
            return false;
        }

        public function forget($event) {}
    };

    $task = new SchedulingEvent($mutex, 'php artisan reports:send');

    $event = new ScheduledTaskFailed($task, new RuntimeException('cron blew up'));

    (new LogScheduleTaskFailure(5))($event);

    $request = acceptOneRequest($this->socket);

    expect($request)->toContain('Scheduled task failed')
        ->and($request)->toContain('reports:send')
        ->and($request)->toContain('cron blew up');
});
