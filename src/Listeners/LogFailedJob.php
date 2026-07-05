<?php

namespace Tailcove\Laravel\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Tailcove\Laravel\Support\ExceptionFormatter;
use Tailcove\Laravel\Support\SafeLogger;

/**
 * Opt-in via tailcove.integrations.failed_jobs — registered on
 * Illuminate\Queue\Events\JobFailed only when enabled.
 */
final class LogFailedJob
{
    public function __construct(private readonly int $exceptionTraceLimit = 10) {}

    public function __invoke(JobFailed $event): void
    {
        SafeLogger::log('error', 'Queued job failed', [
            'connection' => $event->connectionName,
            'job' => $event->job->resolveName(),
            'exception' => ExceptionFormatter::format($event->exception, $this->exceptionTraceLimit),
        ]);
    }
}
