<?php

use Tailcove\Laravel\Jobs\SendLogBatch;

it('delivers the batch via TailcoveHandler::send', function () {
    $job = new SendLogBatch($this->endpoint, 'test-token', [['message' => 'queued']], 1.0);

    // Nothing answers the stand-in socket, so the request itself lands (and
    // is captured below) but the call still times out waiting on a
    // response — that's expected here, this assertion only cares that the
    // bytes queued for delivery were correct.
    try {
        $job->handle();
    } catch (RuntimeException) {
        // expected: see comment above
    }

    $request = acceptOneRequest($this->socket);

    expect($request)->toContain('Authorization: Bearer test-token')
        ->and($request)->toContain('"message":"queued"');
});

it('lets a delivery failure throw so the queue can retry', function () {
    $deadSocket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) explode(':', stream_socket_get_name($deadSocket, false))[1];
    fclose($deadSocket);

    $job = new SendLogBatch("http://127.0.0.1:{$port}/api/v1/logs", null, [['message' => 'x']], 0.5);

    expect(fn () => $job->handle())->toThrow(RuntimeException::class);
});
