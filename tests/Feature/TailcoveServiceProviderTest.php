<?php

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Scheduling\Event as SchedulingEvent;
use Illuminate\Console\Scheduling\EventMutex;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tailcove\Laravel\TailcoveServiceProvider;

beforeEach(function () {
    config(['logging.channels.tailcove' => [
        'driver' => 'tailcove',
        'level' => 'debug',
    ]]);
    config([
        'tailcove.endpoint' => $this->endpoint,
        'tailcove.token' => 'test-token',
        'tailcove.batch_size' => 1,
    ]);
});

it('registers a tailcove log channel that ships to the configured endpoint', function () {
    Log::channel('tailcove')->error('service provider wiring works');

    $request = acceptOneRequest($this->socket);

    expect($request)->toContain('"message":"service provider wiring works"');
});

it('sends nothing when the channel is disabled', function () {
    config(['tailcove.enabled' => false]);

    Log::channel('tailcove')->error('should be dropped');

    $request = acceptOneRequest($this->socket, 0.3);

    expect($request)->toBe('');
});

it('automatically enriches records with environment and hostname', function () {
    Log::channel('tailcove')->error('enriched line');

    $request = acceptOneRequest($this->socket);
    [, $body] = explode("\r\n\r\n", $request, 2);
    $payload = json_decode($body, true);

    expect($payload[0]['context'])
        ->toHaveKey('environment', app()->environment())
        ->toHaveKey('hostname')
        ->toHaveKey('request_id');
});

it('renders a logged exception as structured context', function () {
    Log::channel('tailcove')->error('an error happened', [
        'exception' => new RuntimeException('boom'),
    ]);

    $request = acceptOneRequest($this->socket);
    [, $body] = explode("\r\n\r\n", $request, 2);
    $payload = json_decode($body, true);

    expect($payload[0]['context']['exception'])->toMatchArray([
        'class' => RuntimeException::class,
        'message' => 'boom',
    ]);
});

it('does not register the slow query listener unless enabled', function () {
    (new TailcoveServiceProvider(app()))->boot();

    expect(Event::hasListeners(QueryExecuted::class))->toBeFalse();
});

it('registers the slow query listener when enabled', function () {
    config([
        'tailcove.integrations.slow_queries.enabled' => true,
        'tailcove.integrations.slow_queries.threshold_ms' => 500,
    ]);

    (new TailcoveServiceProvider(app()))->boot();

    $connection = new class
    {
        public function getName(): string
        {
            return 'mysql';
        }
    };

    Event::dispatch(new QueryExecuted('select 1', [], 900.0, $connection));

    $request = acceptOneRequest($this->socket);

    expect($request)->toContain('Slow query detected');
});

it('registers the failed job listener when enabled', function () {
    config(['tailcove.integrations.failed_jobs.enabled' => true]);

    (new TailcoveServiceProvider(app()))->boot();

    $job = new class
    {
        public function resolveName(): string
        {
            return 'App\\Jobs\\Example';
        }
    };

    Event::dispatch(new JobFailed('redis', $job, new RuntimeException('failed')));

    $request = acceptOneRequest($this->socket);

    expect($request)->toContain('Queued job failed');
});

it('registers the schedule failure listener when enabled', function () {
    config(['tailcove.integrations.schedule_failures.enabled' => true]);

    (new TailcoveServiceProvider(app()))->boot();

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

    Event::dispatch(new ScheduledTaskFailed($task, new RuntimeException('cron blew up')));

    $request = acceptOneRequest($this->socket);

    expect($request)->toContain('Scheduled task failed');
});
