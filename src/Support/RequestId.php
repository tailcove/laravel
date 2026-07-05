<?php

namespace Tailcove\Laravel\Support;

/**
 * A stable id shared by every log line written during one request (or one
 * queue job, one Artisan invocation). Bound as a scoped singleton by
 * TailcoveServiceProvider so the whole container graph resolves the same
 * instance; honors an inbound `X-Request-Id` header when the edge/proxy
 * already assigns one, otherwise mints a UUID.
 */
final class RequestId
{
    public function __construct(private readonly string $id) {}

    public function toString(): string
    {
        return $this->id;
    }
}
