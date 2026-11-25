<?php

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tailcove\Laravel\TailcoveServiceProvider;

class LogsThreeLinesJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        foreach (['first', 'second', 'third'] as $line) {
            Log::channel('tailcove')->info("job line {$line}");
        }
    }
}

function configureTailcoveChannel(string $endpoint): void
{
    config(['logging.channels.tailcove' => [
        'driver' => 'tailcove',
        'level' => 'debug',
    ]]);
    config([
        'tailcove.endpoint' => $endpoint,
        'tailcove.token' => 'test-token',
        'tailcove.batch_size' => 50,
        'tailcove.batch_seconds' => 100,
        'tailcove.timeout' => 0.5,
    ]);
}

function fakeQueueJob(): object
{
    return new class
    {
        public function resolveName(): string
        {
            return 'App\\Jobs\\Example';
        }
    };
}

it('sends a job\'s lines when a queue worker finishes the job', function () {
    configureTailcoveChannel($this->endpoint);

    config([
        'queue.default' => 'database',
        'queue.connections.database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ],
    ]);

    Schema::create('jobs', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });

    LogsThreeLinesJob::dispatch();

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true]);

    $request = acceptOneRequest($this->socket);

    expect($request)->toContain('"message":"job line first"')
        ->and($request)->toContain('"message":"job line second"')
        ->and($request)->toContain('"message":"job line third"');
});

it('keeps a short batch buffered until the job is processed', function () {
    configureTailcoveChannel($this->endpoint);

    Log::channel('tailcove')->info('waiting for the job to end');

    expect(acceptOneRequest($this->socket, 0.3))->toBe('');

    Event::dispatch(new JobProcessed('redis', fakeQueueJob()));

    expect(acceptOneRequest($this->socket))->toContain('"message":"waiting for the job to end"');
});

it('flushes when a job throws and when the worker stops', function (object $event) {
    configureTailcoveChannel($this->endpoint);

    Log::channel('tailcove')->error('logged before the event');
    Event::dispatch($event);

    expect(acceptOneRequest($this->socket))->toContain('"message":"logged before the event"');
})->with([
    'job exception' => fn () => new JobExceptionOccurred('redis', fakeQueueJob(), new RuntimeException('boom')),
    'worker stopping' => fn () => new WorkerStopping,
]);

it('leaves jobs on the sync connection to the request\'s own flush', function () {
    configureTailcoveChannel($this->endpoint);

    Log::channel('tailcove')->info('inside a sync job');
    Event::dispatch(new JobProcessed('sync', fakeQueueJob()));

    expect(acceptOneRequest($this->socket, 0.3))->toBe('');
});

it('does nothing when no tailcove channel is configured', function () {
    config(['logging.channels.tailcove' => null]);

    Event::dispatch(new JobProcessed('redis', fakeQueueJob()));
    Event::dispatch(new WorkerStopping);

    expect(acceptOneRequest($this->socket, 0.3))->toBe('');
});

it('listens to Octane events only when Octane is installed', function () {
    expect(Event::hasListeners('Laravel\\Octane\\Events\\TickTerminated'))->toBeFalse();

    if (! class_exists('Laravel\\Octane\\Events\\RequestTerminated')) {
        eval('namespace Laravel\\Octane\\Events; class RequestTerminated {}');
    }

    configureTailcoveChannel($this->endpoint);
    (new TailcoveServiceProvider(app()))->boot();

    Log::channel('tailcove')->info('served by octane');
    Event::dispatch(new ('Laravel\\Octane\\Events\\RequestTerminated'));

    expect(acceptOneRequest($this->socket))->toContain('"message":"served by octane"');
});
