<?php

namespace Tailcove\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Monolog\LogRecord;
use Tailcove\Laravel\Support\ExceptionFormatter;
use Tailcove\Laravel\Support\RequestId;
use Throwable;

/**
 * Automatically enriches every record with environment, hostname, the
 * authed user's id, and the current request id, and renders an
 * `exception` context key as structured data. Runs as a Monolog processor,
 * which means it executes inside Logger::addRecord() — outside
 * TailcoveHandler::write()'s own try/catch — so it must never let anything
 * escape on its own; a failure here falls back to error_log() and returns
 * the record unmodified rather than breaking the caller's Log:: call.
 */
final class ContextProcessor
{
    public function __construct(
        private readonly Application $app,
        private readonly int $exceptionTraceLimit = 10,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        try {
            return $record->with(
                extra: $this->enrichedExtra($record),
                context: $this->enrichedContext($record),
            );
        } catch (Throwable $e) {
            error_log('[tailcove] context enrichment failed: '.$e->getMessage());

            return $record;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichedExtra(LogRecord $record): array
    {
        $extra = $record->extra;

        $extra['environment'] = $this->app->environment();
        $extra['hostname'] = gethostname() ?: null;

        try {
            if (Auth::hasUser()) {
                $extra['user_id'] = Auth::id();
            }
        } catch (Throwable) {
            // No auth guard configured (or resolvable) in this context —
            // user_id just isn't attached, the rest of enrichment proceeds.
        }

        if ($this->app->bound(RequestId::class)) {
            $extra['request_id'] = $this->app->make(RequestId::class)->toString();
        }

        return $extra;
    }

    /**
     * @return array<string, mixed>
     */
    private function enrichedContext(LogRecord $record): array
    {
        $context = $record->context;

        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $context['exception'] = ExceptionFormatter::format($context['exception'], $this->exceptionTraceLimit);
        }

        return $context;
    }
}
