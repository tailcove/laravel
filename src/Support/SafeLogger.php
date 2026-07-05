<?php

namespace Tailcove\Laravel\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared "never throw" entry point the optional integrations (slow
 * queries, failed jobs, schedule failures) use to reach the 'tailcove'
 * channel directly. Isolated here so a host app that enables an
 * integration without adding the 'tailcove' channel to config/logging.php
 * gets a swallowed error_log() line instead of a broken query/job/schedule
 * run.
 */
final class SafeLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $level, string $message, array $context): void
    {
        try {
            Log::channel('tailcove')->log($level, $message, $context);
        } catch (Throwable $e) {
            error_log('[tailcove] integration logging failed: '.$e->getMessage());
        }
    }
}
