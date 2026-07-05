<?php

use Illuminate\Queue\Events\JobFailed;
use Tailcove\Laravel\Listeners\LogFailedJob;

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

it('logs failed queue jobs with structured exception context', function () {
    $job = new class
    {
        public function resolveName(): string
        {
            return 'App\\Jobs\\SendWelcomeEmail';
        }
    };

    $event = new JobFailed('redis', $job, new RuntimeException('boom'));

    (new LogFailedJob(5))($event);

    $request = acceptOneRequest($this->socket);

    expect($request)->toContain('Queued job failed')
        ->and($request)->toContain('App\\\\Jobs\\\\SendWelcomeEmail')
        ->and($request)->toContain('RuntimeException')
        ->and($request)->toContain('"connection":"redis"');
});
