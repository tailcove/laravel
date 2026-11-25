<?php

namespace Tailcove\Laravel\Listeners;

use Tailcove\Laravel\TailcoveHandler;
use Throwable;

/**
 * Sends buffered log lines at the end of each unit of work in a process
 * that does not exit between them: after every queue job (and when the
 * worker stops), and after every Octane request, task and tick. Without
 * it, a job that logs a few lines and then leaves the worker idle keeps
 * those lines in memory until the next log call or the worker restarts.
 */
class FlushLogBuffers
{
    public function __invoke(?object $event = null): void
    {
        try {
            // A job on the sync connection runs inside the current request,
            // whose own shutdown flush already covers it.
            if ($event !== null && ($event->connectionName ?? null) === 'sync') {
                return;
            }

            TailcoveHandler::flushAll();
        } catch (Throwable $e) {
            error_log('[tailcove] failed to flush log buffers: '.$e->getMessage());
        }
    }
}
