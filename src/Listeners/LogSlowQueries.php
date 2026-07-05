<?php

namespace Tailcove\Laravel\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Tailcove\Laravel\Support\SafeLogger;

/**
 * Opt-in via tailcove.integrations.slow_queries — registered on DB::listen()
 * by TailcoveServiceProvider only when enabled, so hosts that don't want
 * this pay no overhead.
 */
final class LogSlowQueries
{
    public function __construct(private readonly int $thresholdMs) {}

    public function __invoke(QueryExecuted $event): void
    {
        if ($event->time < $this->thresholdMs) {
            return;
        }

        SafeLogger::log('warning', 'Slow query detected', [
            'sql' => $event->sql,
            'bindings' => $event->bindings,
            'time_ms' => $event->time,
            'connection' => $event->connectionName,
        ]);
    }
}
