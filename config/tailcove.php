<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ingest endpoint
    |--------------------------------------------------------------------------
    |
    | Your Tailcove ingest URL and the source's ingest token (from the
    | source's "Copy your ingest token now" screen). Never commit the
    | token — set TAILCOVE_TOKEN in your .env.
    |
    */

    'endpoint' => env('TAILCOVE_ENDPOINT', 'https://ingest.tailcove.dev/api/v1/logs'),

    'token' => env('TAILCOVE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Enable / minimum level
    |--------------------------------------------------------------------------
    |
    | Flip TAILCOVE_ENABLED off (e.g. per-environment) to keep the channel
    | registered but silent — records are dropped before anything is
    | buffered or sent. `level` is the minimum Monolog level this handler
    | processes; it's independent of the level set on the channel itself in
    | config/logging.php.
    |
    */

    'enabled' => env('TAILCOVE_ENABLED', true),

    'level' => env('TAILCOVE_LEVEL', 'debug'),

    /*
    |--------------------------------------------------------------------------
    | Batching
    |--------------------------------------------------------------------------
    |
    | Records are buffered in memory and flushed as one batch once either
    | threshold is hit — never one HTTP request per log line.
    |
    */

    'batch_size' => (int) env('TAILCOVE_BATCH_SIZE', 50),

    'batch_seconds' => (float) env('TAILCOVE_BATCH_SECONDS', 2.0),

    /*
    |--------------------------------------------------------------------------
    | Dispatch
    |--------------------------------------------------------------------------
    |
    | "shutdown" (default) sends the batch from a register_shutdown_function
    | callback, after the response has already been sent to the browser —
    | logging never adds latency to the request. "queue" instead dispatches
    | a queued job, for apps that would rather keep even that shutdown-time
    | work off the web worker (e.g. queue workers are already scaled for it).
    |
    */

    'dispatch' => env('TAILCOVE_DISPATCH', 'shutdown'),

    'queue' => env('TAILCOVE_QUEUE_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */

    'timeout' => (float) env('TAILCOVE_HTTP_TIMEOUT', 2.0),

    /*
    |--------------------------------------------------------------------------
    | Context enrichment
    |--------------------------------------------------------------------------
    |
    | Every record automatically gets environment, hostname, the authed
    | user's id (when available), and a per-request id attached. When a
    | record's context carries an `exception` key, it's rendered as
    | structured data (class, message, file:line, trace) instead of relying
    | on Monolog's default string formatting. `exception_trace_limit` caps
    | how many stack frames are kept, so one exception can't blow out a
    | batch's payload size.
    |
    */

    'exception_trace_limit' => (int) env('TAILCOVE_EXCEPTION_TRACE_LIMIT', 10),

    /*
    |--------------------------------------------------------------------------
    | Optional integrations
    |--------------------------------------------------------------------------
    |
    | Each of these is off by default. When enabled, they log through the
    | 'tailcove' channel directly (bypassing your default log stack) so this
    | telemetry always reaches Tailcove even if you don't call Log:: for it
    | yourself. A failure in any of these never throws into your app — it's
    | caught and dropped, same as everything else in this package.
    |
    */

    'integrations' => [

        'slow_queries' => [
            'enabled' => env('TAILCOVE_LOG_SLOW_QUERIES', false),
            'threshold_ms' => (int) env('TAILCOVE_SLOW_QUERY_THRESHOLD_MS', 500),
        ],

        'failed_jobs' => [
            'enabled' => env('TAILCOVE_LOG_FAILED_JOBS', false),
        ],

        'schedule_failures' => [
            'enabled' => env('TAILCOVE_LOG_SCHEDULE_FAILURES', false),
        ],

    ],

];
