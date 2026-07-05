<?php

namespace Tailcove\Laravel\Support;

use Throwable;

/**
 * Renders a Throwable as structured data instead of Monolog's default
 * string interpolation, so Tailcove's UI can show class/location/trace as
 * distinct fields. The trace is trimmed to `$traceLimit` frames — an
 * exception's full trace can be large enough to dwarf the rest of a batch.
 */
final class ExceptionFormatter
{
    /**
     * @return array{class: string, message: string, location: string, trace: list<string>}
     */
    public static function format(Throwable $exception, int $traceLimit = 10): array
    {
        return [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
            'location' => sprintf('%s:%d', $exception->getFile(), $exception->getLine()),
            'trace' => array_slice(explode("\n", $exception->getTraceAsString()), 0, $traceLimit),
        ];
    }
}
