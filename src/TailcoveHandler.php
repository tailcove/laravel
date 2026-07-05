<?php

namespace Tailcove\Laravel;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;
use Tailcove\Laravel\Jobs\SendLogBatch;
use Throwable;

/**
 * Batches log records and sends them to a Tailcove source's ingest
 * endpoint — up to `batch_size` lines or `batch_seconds`, whichever comes
 * first, per the log-ingestion skill. Never lets a logging failure
 * propagate into the host app: every failure is swallowed and falls back
 * to PHP's own error_log().
 */
class TailcoveHandler extends AbstractProcessingHandler
{
    /** @var list<array<string, mixed>> */
    private array $buffer = [];

    private ?float $batchStartedAt = null;

    private bool $shutdownRegistered = false;

    /**
     * @param  int|string|Level  $level
     */
    public function __construct(
        private readonly string $endpoint,
        private readonly ?string $token,
        private readonly int $batchSize = 50,
        private readonly float $batchSeconds = 2.0,
        private readonly string $dispatch = 'shutdown',
        private readonly ?string $queueConnection = null,
        private readonly float $httpTimeout = 2.0,
        $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $this->flushIfBatchExpired();

            $this->buffer[] = $this->normalize($record);
            $this->batchStartedAt ??= microtime(true);
            $this->registerShutdownFlush();

            if (count($this->buffer) >= $this->batchSize) {
                $this->flush();
            }
        } catch (Throwable $e) {
            $this->fallback($e, $record);
        }
    }

    /**
     * Sends whatever is buffered right now, regardless of size or age.
     * Safe to call directly (e.g. before a worker process exits).
     */
    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $batch = $this->buffer;
        $this->buffer = [];
        $this->batchStartedAt = null;

        try {
            if ($this->dispatch === 'queue') {
                SendLogBatch::dispatch($this->endpoint, $this->token, $batch, $this->httpTimeout)
                    ->onConnection($this->queueConnection);

                return;
            }

            self::send($this->endpoint, $this->token, $batch, $this->httpTimeout);
        } catch (Throwable $e) {
            error_log('[tailcove] failed to send log batch: '.$e->getMessage());
        }
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     *
     * @throws RuntimeException when the request fails or the endpoint
     *                          responds with a non-2xx status — callers
     *                          (flush() in shutdown mode, the queued job in
     *                          queue mode) are responsible for deciding what
     *                          to do with that: swallow it, or let the
     *                          queue's own retry/backoff handle it.
     */
    public static function send(string $endpoint, ?string $token, array $batch, float $timeout): void
    {
        $body = json_encode($batch);

        if ($body === false) {
            return;
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array_filter([
                'Content-Type: application/json',
                $token !== null ? "Authorization: Bearer {$token}" : null,
            ]),
            CURLOPT_TIMEOUT_MS => (int) ($timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) ($timeout * 1000),
            CURLOPT_RETURNTRANSFER => true,
        ]);

        curl_exec($ch);
        $errno = curl_errno($ch);
        $errorMessage = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        self::assertDelivered($errno, $errorMessage, $status);
    }

    /**
     * Classifies a completed curl attempt as delivered or failed. Split out
     * from send() so the failure logic (what counts as "failed") is
     * unit-testable without standing up a live socket for every case.
     */
    public static function assertDelivered(int $errno, string $errorMessage, int $status): void
    {
        if ($errno !== 0) {
            throw new RuntimeException("tailcove: request to ingest endpoint failed ({$errorMessage})");
        }

        if ($status >= 300) {
            throw new RuntimeException("tailcove: ingest endpoint responded with status {$status}");
        }
    }

    private function flushIfBatchExpired(): void
    {
        if ($this->batchStartedAt === null) {
            return;
        }

        if ((microtime(true) - $this->batchStartedAt) >= $this->batchSeconds) {
            $this->flush();
        }
    }

    private function registerShutdownFlush(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $this->shutdownRegistered = true;

        register_shutdown_function(function (): void {
            $this->flush();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(LogRecord $record): array
    {
        // $record->extra carries the automatic enrichment added by
        // ContextProcessor (environment, hostname, user_id, request_id);
        // folded into context so it lands in the ingest payload. Explicit
        // context keys win on collision.
        return [
            'message' => $record->message,
            'level' => strtolower($record->level->getName()),
            'context' => array_merge($record->extra, $record->context),
            'host' => gethostname() ?: '',
            'ts' => $record->datetime->format('Y-m-d\TH:i:s.vP'),
        ];
    }

    private function fallback(Throwable $exception, LogRecord $record): void
    {
        error_log(sprintf(
            '[tailcove] handler failure, falling back to error_log: %s | original message: %s',
            $exception->getMessage(),
            $record->message,
        ));
    }
}
