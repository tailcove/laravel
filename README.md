# tailcove/laravel

Monolog handler and Laravel service provider for shipping application logs to
[Tailcove](https://tailcove.com). Batches up to 50 lines (or 2 seconds,
whichever comes first) and sends them from `register_shutdown_function` —
after the response has already gone out, so logging never adds latency to a
request.

A delivery failure never throws into your application. Every entry point
(the handler, the queued job, the optional integrations) swallows its own
errors and falls back to PHP's `error_log()`.

## Install

```bash
composer require tailcove/laravel
```

Laravel's package auto-discovery registers `TailcoveServiceProvider`
automatically. Set two env vars:

```env
TAILCOVE_ENDPOINT=https://ingest.tailcove.com/api/v1/logs
TAILCOVE_TOKEN=your-source-ingest-token
```

Add a `tailcove` channel to `config/logging.php`:

```php
'channels' => [
    // ...
    'tailcove' => [
        'driver' => 'tailcove',
        'level' => env('LOG_LEVEL', 'debug'),
    ],
],
```

Then include it in your stack:

```env
LOG_STACK=single,tailcove
```

That's it — `Log::info(...)`, `Log::error(...)`, and any exception you log
now ship to your Tailcove source.

## Configure

```bash
php artisan vendor:publish --tag=tailcove-config
```

Every option is set from `config/tailcove.php`, and every one of them can be
overridden per-channel (e.g. if you run more than one Tailcove-backed
channel) by setting the same key directly on the channel's config array in
`config/logging.php`.

| Env var | Default | Purpose |
|---|---|---|
| `TAILCOVE_ENDPOINT` | `https://ingest.tailcove.com/api/v1/logs` | Your source's ingest URL. |
| `TAILCOVE_TOKEN` | — | The source's ingest token. Never commit this. |
| `TAILCOVE_ENABLED` | `true` | Set `false` to keep the channel registered but silent — nothing is buffered or sent. |
| `TAILCOVE_LEVEL` | `debug` | Minimum Monolog level the handler processes. |
| `TAILCOVE_BATCH_SIZE` | `50` | Flush once this many records are buffered. |
| `TAILCOVE_BATCH_SECONDS` | `2.0` | Flush once this many seconds have passed since the first buffered record, whichever comes first. |
| `TAILCOVE_DISPATCH` | `shutdown` | `shutdown` sends inline from `register_shutdown_function`; `queue` dispatches `Tailcove\Laravel\Jobs\SendLogBatch` instead. |
| `TAILCOVE_QUEUE_CONNECTION` | — | Queue connection used when `TAILCOVE_DISPATCH=queue`. |
| `TAILCOVE_HTTP_TIMEOUT` | `2.0` | Seconds before a delivery attempt gives up. |
| `TAILCOVE_EXCEPTION_TRACE_LIMIT` | `10` | Max stack frames kept when rendering a logged exception. |
| `TAILCOVE_LOG_SLOW_QUERIES` | `false` | Ship slow-query telemetry (see [Integrations](#integrations)). |
| `TAILCOVE_SLOW_QUERY_THRESHOLD_MS` | `500` | Threshold for the slow-query integration. |
| `TAILCOVE_LOG_FAILED_JOBS` | `false` | Ship failed-job telemetry. |
| `TAILCOVE_LOG_SCHEDULE_FAILURES` | `false` | Ship scheduled-task-failure telemetry. |

## Dispatch modes

- `shutdown` (default) — sends inline from a shutdown function. No queue
  worker required.
- `queue` — dispatches a `Tailcove\Laravel\Jobs\SendLogBatch` job instead, for
  apps that would rather keep this off the web worker entirely. Delivery
  failures let the queue's own retry/backoff take over (3 tries, backing off
  5s/30s/120s) instead of being swallowed immediately:

  ```env
  TAILCOVE_DISPATCH=queue
  TAILCOVE_QUEUE_CONNECTION=redis
  ```

  Security note: in this mode, the source's ingest token travels inside the
  serialized job payload on *your* queue backend, and lands in your
  `failed_jobs` table/store if all retries are exhausted. Anyone with access
  to that backend can read it. If that's a concern, either keep
  `TAILCOVE_DISPATCH=shutdown` or restrict access to your queue store the
  same way you would any other secret-bearing table.

## Automatic context enrichment

Every record gets, without any code changes on your end:

- `environment` — `app()->environment()`
- `hostname` — `gethostname()`
- `user_id` — the currently authed user's id, when a guard has one
- `request_id` — shared by every log line written during one request (reuses
  an inbound `X-Request-Id` header if your edge/proxy sets one, otherwise
  mints a UUID)

When a record's context carries an `exception` key (as it does when you call
`report($e)` or `Log::error('...', ['exception' => $e])`), it's rendered as
structured data instead of Monolog's default string interpolation:

```php
'exception' => [
    'class' => 'RuntimeException',
    'message' => 'Something broke',
    'location' => '/app/Http/Controllers/OrderController.php:42',
    'trace' => ['#0 /app/...', '#1 /app/...', /* up to TAILCOVE_EXCEPTION_TRACE_LIMIT frames */],
],
```

Enrichment never throws — if it fails for any reason (e.g. no auth guard
configured in the current context), the record ships without the affected
field rather than breaking the log call.

## Integrations

Off by default, each one logs straight through the `tailcove` channel (so
this telemetry ships even if you don't call `Log::` for it yourself). A
failure in any integration is caught and dropped, never thrown into your
app.

```env
TAILCOVE_LOG_SLOW_QUERIES=true
TAILCOVE_SLOW_QUERY_THRESHOLD_MS=500

TAILCOVE_LOG_FAILED_JOBS=true

TAILCOVE_LOG_SCHEDULE_FAILURES=true
```

- **Slow queries** — logs any query at or above the threshold, with `sql`,
  `bindings`, `time_ms`, and `connection`.
- **Failed queue jobs** — logs `Illuminate\Queue\Events\JobFailed`, with the
  job name, connection, and the structured exception.
- **Scheduled task failures** — logs `Illuminate\Console\Events\ScheduledTaskFailed`,
  with the command and the structured exception.

## Failure handling

A delivery failure never throws into your application — it's caught and
written to PHP's own `error_log()` as a local fallback. Logging is
best-effort by design; it should never be the reason a request fails.

## Upgrading

### From the pre-1.0 skeleton (handler + service provider only)

- `config/tailcove.php` gained `enabled`, `level`, `exception_trace_limit`,
  and `integrations.*` keys. If you've already published the config file,
  re-run `php artisan vendor:publish --tag=tailcove-config --force` (or add
  the new keys by hand) to pick them up — omitted keys fall back to their
  defaults, so nothing breaks if you skip this, but the new integrations
  stay off until the keys exist.
- `TailcoveHandler::send()` now throws on delivery failure instead of always
  succeeding silently. This only matters if you called it directly; `flush()`
  and the queued job's normal usage are unaffected (the former still
  swallows, the latter now retries as intended).
- Log payloads sent to Tailcove now include an automatically-enriched
  `context` (environment, hostname, user_id, request_id) on every line. If
  you have anything downstream matching on the exact shape of `context`,
  account for these additional keys.
