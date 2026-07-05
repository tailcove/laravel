<?php

namespace Tailcove\Laravel\Listeners;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Tailcove\Laravel\Support\ExceptionFormatter;
use Tailcove\Laravel\Support\SafeLogger;

/**
 * Opt-in via tailcove.integrations.schedule_failures — registered on
 * Illuminate\Console\Events\ScheduledTaskFailed only when enabled.
 */
final class LogScheduleTaskFailure
{
    public function __construct(private readonly int $exceptionTraceLimit = 10) {}

    public function __invoke(ScheduledTaskFailed $event): void
    {
        SafeLogger::log('error', 'Scheduled task failed', [
            'command' => $event->task->command ?? $event->task->description ?? null,
            'exception' => ExceptionFormatter::format($event->exception, $this->exceptionTraceLimit),
        ]);
    }
}
