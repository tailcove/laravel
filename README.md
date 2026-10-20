<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/tailcove/.github/main/profile/assets/packages/laravel-dark.svg">
  <source media="(prefers-color-scheme: light)" srcset="https://raw.githubusercontent.com/tailcove/.github/main/profile/assets/packages/laravel-light.svg">
  <img alt="tailcove/laravel: Tailcove for Laravel" src="https://raw.githubusercontent.com/tailcove/.github/main/profile/assets/packages/laravel-dark.svg" width="100%">
</picture>

<p align="center">
  <a href="https://tailcove.dev/docs/sending-logs/laravel-package"><img alt="Documentation" src="https://img.shields.io/badge/Documentation-2DD4BF?style=for-the-badge&logoColor=0A0A0A"></a>
  <a href="https://app.tailcove.dev/register"><img alt="Get started free" src="https://img.shields.io/badge/Get%20started%20free-0A0A0A?style=for-the-badge"></a>
  <a href="https://github.com/tailcove/laravel/issues/new/choose"><img alt="Report a bug" src="https://img.shields.io/badge/Report%20a%20bug-0A0A0A?style=for-the-badge"></a>
</p>

<p align="center">
  <a href="https://packagist.org/packages/tailcove/laravel"><img alt="Packagist version" src="https://img.shields.io/packagist/v/tailcove/laravel?color=2DD4BF&labelColor=0A0A0A"></a>
  <a href="https://packagist.org/packages/tailcove/laravel"><img alt="Packagist downloads" src="https://img.shields.io/packagist/dt/tailcove/laravel?color=2DD4BF&labelColor=0A0A0A"></a>
  <img alt="PHP 8.2+" src="https://img.shields.io/packagist/dependency-v/tailcove/laravel/php?color=2DD4BF&labelColor=0A0A0A">
  <img alt="Laravel 11, 12, 13" src="https://img.shields.io/badge/laravel-11%20%7C%2012%20%7C%2013-2DD4BF?labelColor=0A0A0A">
  <a href="LICENSE"><img alt="MIT license" src="https://img.shields.io/badge/license-MIT-2DD4BF?labelColor=0A0A0A"></a>
</p>

<br>

<h3 align="center">Send your Laravel logs to Tailcove with one <code>composer require</code>.</h3>

<br>

<table>
  <tr>
    <td width="33%" valign="top">
      <h4>🚀 &nbsp;No added latency</h4>
      Logs are sent from a shutdown function, after the response has already gone out.
    </td>
    <td width="33%" valign="top">
      <h4>🛡️ &nbsp;Safe</h4>
      A failed delivery is caught and written to <code>error_log()</code>. It never fails a request.
    </td>
    <td width="33%" valign="top">
      <h4>🏷️ &nbsp;Context for free</h4>
      Environment, hostname, signed-in user and a request ID on every line.
    </td>
  </tr>
  <tr>
    <td width="33%" valign="top">
      <h4>🧩 &nbsp;Exceptions as data</h4>
      <code>report($e)</code> arrives with class, message, location and trace.
    </td>
    <td width="33%" valign="top">
      <h4>🔧 &nbsp;Optional extras</h4>
      Slow queries, failed jobs and scheduler failures, one setting each.
    </td>
    <td width="33%" valign="top">
      <h4>📬 &nbsp;Queue mode</h4>
      Prefer the worker? Switch to a queued job with retries.
    </td>
  </tr>
</table>

<br>

## Install

```bash
composer require tailcove/laravel
```

Requires PHP 8.2+ and Laravel 11, 12 or 13. Laravel's package auto-discovery registers the service provider for you.

## Quick start

**1. Get a token.** Create a source in your [Tailcove dashboard](https://app.tailcove.dev) and copy its ingest token. It is shown only once, so save it straight into your `.env`.

**2. Add two lines to `.env`.**

```env
TAILCOVE_TOKEN=your-source-ingest-token
LOG_STACK=single,tailcove
```

**3. Add the channel to `config/logging.php`.**

```php
'channels' => [
    // ...
    'tailcove' => [
        'driver' => 'tailcove',
        'level' => env('LOG_LEVEL', 'debug'),
    ],
],
```

That's it. `Log::info(...)`, `Log::error(...)` and any exception you report now appear in Tailcove search and live tail within a couple of seconds.

## Configuration

Publish the config file if you want to edit it:

```bash
php artisan vendor:publish --tag=tailcove-config
```

Every option lives in `config/tailcove.php` and can be set with an environment variable. To run more than one Tailcove channel, set the same key directly on a channel in `config/logging.php` to override it for that channel.

| Environment variable | Default | What it does |
|---|---|---|
| `TAILCOVE_TOKEN` | — | Your source's ingest token. **Required.** Never commit it. |
| `TAILCOVE_ENDPOINT` | `https://ingest.tailcove.dev/api/v1/logs` | The ingest URL. Only change this if you self-host. |
| `TAILCOVE_ENABLED` | `true` | Set `false` to keep the channel registered but silent. |
| `TAILCOVE_LEVEL` | `debug` | Lowest Monolog level the handler processes. |
| `TAILCOVE_BATCH_SIZE` | `50` | Send once this many records are buffered. |
| `TAILCOVE_BATCH_SECONDS` | `2.0` | Send once this many seconds have passed since the first buffered record. |
| `TAILCOVE_DISPATCH` | `shutdown` | `shutdown` sends after the response. `queue` uses a queued job. See [Sending modes](#sending-modes). |
| `TAILCOVE_QUEUE_CONNECTION` | — | Queue connection to use when `TAILCOVE_DISPATCH=queue`. |
| `TAILCOVE_HTTP_TIMEOUT` | `2.0` | Seconds a delivery attempt may take before giving up. |
| `TAILCOVE_EXCEPTION_TRACE_LIMIT` | `10` | Most stack frames kept when an exception is logged. |
| `TAILCOVE_LOG_SLOW_QUERIES` | `false` | Log slow queries. See [Extras](#extras). |
| `TAILCOVE_SLOW_QUERY_THRESHOLD_MS` | `500` | How slow a query must be to be logged. |
| `TAILCOVE_LOG_FAILED_JOBS` | `false` | Log failed queue jobs. |
| `TAILCOVE_LOG_SCHEDULE_FAILURES` | `false` | Log failed scheduled tasks. |

## What gets added automatically

| Field | Value |
|---|---|
| `environment` | `app()->environment()` |
| `hostname` | `gethostname()` |
| `user_id` | The signed-in user's id, when a guard has one |
| `request_id` | Shared by every line in one request. Reuses an incoming `X-Request-Id` header, or creates a UUID. |

If adding any of these fails, for example when no auth guard exists in that context, the line is still sent without that field. It never breaks the log call.

When a line's context has an `exception` key, as with `report($e)` or `Log::error('...', ['exception' => $e])`, it is sent as structured data:

```php
'exception' => [
    'class' => 'RuntimeException',
    'message' => 'Something broke',
    'location' => '/app/Http/Controllers/OrderController.php:42',
    'trace' => ['#0 /app/...', '#1 /app/...'],  // up to TAILCOVE_EXCEPTION_TRACE_LIMIT frames
],
```

## Sending modes

**`shutdown` (default).** Sends from a shutdown function, right after the response. No queue worker needed.

**`queue`.** Dispatches a `Tailcove\Laravel\Jobs\SendLogBatch` job instead, for apps that want sending off the web worker completely. A failed delivery uses your queue's own retries (3 tries, waiting 5, 30 and 120 seconds) and isn't dropped straight away.

```env
TAILCOVE_DISPATCH=queue
TAILCOVE_QUEUE_CONNECTION=redis
```

> **Security note:** in queue mode the ingest token travels inside the serialized job on your queue backend, and is stored in `failed_jobs` if every retry fails. Anyone who can read that store can read the token. If that matters, stay on `shutdown`, or lock down your queue store as you would any table holding secrets.

## Extras

Each is off by default and logs through the `tailcove` channel, so the data ships even if you never call `Log::` yourself. A failure in any of them is caught and dropped.

```env
TAILCOVE_LOG_SLOW_QUERIES=true
TAILCOVE_SLOW_QUERY_THRESHOLD_MS=500

TAILCOVE_LOG_FAILED_JOBS=true

TAILCOVE_LOG_SCHEDULE_FAILURES=true
```

| Setting | What is logged |
|---|---|
| Slow queries | Queries at or above the threshold, with `sql`, `bindings`, `time_ms` and `connection`. |
| Failed queue jobs | Each `JobFailed` event, with the job name, connection and the exception. |
| Scheduled task failures | Each `ScheduledTaskFailed` event, with the command and the exception. |

## When delivery fails

A failed delivery never throws into your app. It is caught and written to PHP's `error_log()` as a local fallback. Logging is best-effort by design.

## Help and feedback

- **Docs:** [tailcove.dev/docs/sending-logs/laravel-package](https://tailcove.dev/docs/sending-logs/laravel-package)
- **Bugs and feature requests:** [open an issue](https://github.com/tailcove/laravel/issues/new/choose)
- **Security problems:** please email **security@tailcove.dev** instead of opening a public issue.

## Other Tailcove libraries

[Node.js](https://github.com/tailcove/node) · [Python](https://github.com/tailcove/python) · [Browser](https://github.com/tailcove/browser)

## License

[MIT](LICENSE) © AKCybex

<br>

<p align="center">
  <sub>Built by <a href="https://akcybex.com">AKCybex</a> &nbsp;·&nbsp; <a href="https://tailcove.dev">tailcove.dev</a></sub>
</p>
