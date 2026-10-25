<?php

namespace Tailcove\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Tailcove\Laravel\TailcoveHandler;

/**
 * The "queue" dispatch mode: hands the batch to a queue worker instead of
 * sending it inline from register_shutdown_function. TailcoveHandler::send()
 * throws on delivery failure so the queue's own retry/backoff can take over;
 * once retries are exhausted the batch lands in failed_jobs rather than
 * retrying forever or throwing into the surrounding application.
 */
class SendLogBatch implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 30, 120];

    /**
     * @param  list<array<string, mixed>>  $batch
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly ?string $token,
        public readonly array $batch,
        public readonly float $timeout,
    ) {}

    public function handle(): void
    {
        TailcoveHandler::send($this->endpoint, $this->token, $this->batch, $this->timeout);
    }
}
