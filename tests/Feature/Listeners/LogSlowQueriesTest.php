<?php

use Illuminate\Database\Events\QueryExecuted;
use Tailcove\Laravel\Listeners\LogSlowQueries;

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

function tailcoveTestConnection(string $name = 'mysql'): object
{
    return new class($name)
    {
        public function __construct(private readonly string $name) {}

        public function getName(): string
        {
            return $this->name;
        }
    };
}

it('logs queries at or above the threshold', function () {
    $event = new QueryExecuted('select * from users', [], 620.5, tailcoveTestConnection());

    (new LogSlowQueries(500))($event);

    $request = acceptOneRequest($this->socket);

    expect($request)->toContain('Slow query detected')
        ->and($request)->toContain('"time_ms":620.5');
});

it('ignores queries under the threshold', function () {
    $event = new QueryExecuted('select 1', [], 10.0, tailcoveTestConnection());

    (new LogSlowQueries(500))($event);

    $request = acceptOneRequest($this->socket, 0.3);

    expect($request)->toBe('');
});
