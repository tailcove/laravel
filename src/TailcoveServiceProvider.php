<?php

namespace Tailcove\Laravel;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Tailcove\Laravel\Listeners\FlushLogBuffers;
use Tailcove\Laravel\Listeners\LogFailedJob;
use Tailcove\Laravel\Listeners\LogScheduleTaskFailure;
use Tailcove\Laravel\Listeners\LogSlowQueries;
use Tailcove\Laravel\Support\RequestId;

class TailcoveServiceProvider extends ServiceProvider
{
    /**
     * Octane's end-of-work events; listened to only when Octane is installed.
     *
     * @var list<string>
     */
    private const OCTANE_EVENTS = [
        'Laravel\\Octane\\Events\\RequestTerminated',
        'Laravel\\Octane\\Events\\TaskTerminated',
        'Laravel\\Octane\\Events\\TickTerminated',
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tailcove.php', 'tailcove');

        $this->app->scoped(RequestId::class, function ($app) {
            $header = $app->bound('request') ? $app['request']->header('X-Request-Id') : null;

            return new RequestId($header ?: (string) Str::uuid());
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/tailcove.php' => config_path('tailcove.php'),
            ], 'tailcove-config');
        }

        // Add 'tailcove' to config/logging.php's channels (driver =>
        // 'tailcove') and to LOG_STACK to start shipping logs.
        Log::extend('tailcove', function ($app, array $config) {
            $tailcove = $app['config']->get('tailcove', []);

            if (! ($config['enabled'] ?? $tailcove['enabled'] ?? true)) {
                return new Logger('tailcove', [new NullHandler]);
            }

            $handler = new TailcoveHandler(
                endpoint: $config['endpoint'] ?? $tailcove['endpoint'],
                token: $config['token'] ?? $tailcove['token'],
                batchSize: $config['batch_size'] ?? $tailcove['batch_size'],
                batchSeconds: $config['batch_seconds'] ?? $tailcove['batch_seconds'],
                dispatch: $config['dispatch'] ?? $tailcove['dispatch'],
                queueConnection: $config['queue'] ?? $tailcove['queue'],
                httpTimeout: $config['timeout'] ?? $tailcove['timeout'],
                level: $config['level'] ?? $tailcove['level'] ?? 'debug',
            );

            $handler->pushProcessor(new ContextProcessor(
                app: $app,
                exceptionTraceLimit: $tailcove['exception_trace_limit'] ?? 10,
            ));

            return new Logger('tailcove', [$handler]);
        });

        $this->registerIntegrations();
        $this->registerFlushListeners();
    }

    /**
     * Queue workers, Horizon and Octane never reach the shutdown flush
     * between units of work, so buffered lines are sent after each one.
     * Registered after the integrations, so a failed job's own log line
     * goes out in the same flush.
     */
    private function registerFlushListeners(): void
    {
        $events = [
            JobProcessed::class,
            JobExceptionOccurred::class,
            JobFailed::class,
            WorkerStopping::class,
        ];

        foreach (self::OCTANE_EVENTS as $octaneEvent) {
            if (class_exists($octaneEvent)) {
                $events[] = $octaneEvent;
            }
        }

        Event::listen($events, FlushLogBuffers::class);
    }

    private function registerIntegrations(): void
    {
        $integrations = $this->app['config']->get('tailcove.integrations', []);
        $traceLimit = $this->app['config']->get('tailcove.exception_trace_limit', 10);

        if ($integrations['slow_queries']['enabled'] ?? false) {
            Event::listen(QueryExecuted::class, new LogSlowQueries(
                $integrations['slow_queries']['threshold_ms'] ?? 500,
            ));
        }

        if ($integrations['failed_jobs']['enabled'] ?? false) {
            Event::listen(JobFailed::class, new LogFailedJob($traceLimit));
        }

        if ($integrations['schedule_failures']['enabled'] ?? false) {
            Event::listen(ScheduledTaskFailed::class, new LogScheduleTaskFailure($traceLimit));
        }
    }
}
