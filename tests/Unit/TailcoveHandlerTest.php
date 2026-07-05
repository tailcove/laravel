<?php

use Tailcove\Laravel\TailcoveHandler;

it('buffers records and flushes once the batch size is reached', function () {
    $handler = new TailcoveHandler(
        endpoint: $this->endpoint,
        token: 'test-token',
        batchSize: 2,
        batchSeconds: 100,
        dispatch: 'shutdown',
        queueConnection: null,
        httpTimeout: 1.0,
    );

    $handler->handle(record('first'));
    $handler->handle(record('second'));

    $request = acceptOneRequest($this->socket);

    expect($request)->toContain('POST /api/v1/logs')
        ->and($request)->toContain('Authorization: Bearer test-token')
        ->and($request)->toContain('"message":"first"')
        ->and($request)->toContain('"message":"second"');
});

it('sends whatever is buffered when flush() is called directly', function () {
    $handler = new TailcoveHandler(
        endpoint: $this->endpoint,
        token: null,
        batchSize: 50,
        batchSeconds: 100,
        dispatch: 'shutdown',
        queueConnection: null,
        httpTimeout: 1.0,
    );

    $handler->handle(record('only one line'));
    $handler->flush();

    $request = acceptOneRequest($this->socket);

    expect($request)->toContain('"message":"only one line"')
        ->and($request)->not->toContain('Authorization');
});

it('normalizes a record into the ingest payload shape', function () {
    $handler = new TailcoveHandler(
        endpoint: $this->endpoint,
        token: 'tok',
        batchSize: 1,
        batchSeconds: 100,
        dispatch: 'shutdown',
        queueConnection: null,
        httpTimeout: 1.0,
    );

    $handler->handle(record('boom', ['user_id' => 42]));

    $request = acceptOneRequest($this->socket);
    [, $body] = explode("\r\n\r\n", $request, 2);
    $payload = json_decode($body, true);

    expect($payload)->toHaveCount(1);
    expect($payload[0])->toMatchArray([
        'message' => 'boom',
        'level' => 'error',
        'context' => ['user_id' => 42],
    ]);
    expect($payload[0])->toHaveKey('host');
    expect($payload[0])->toHaveKey('ts');
});

it('throws when the request cannot reach the endpoint', function () {
    $deadSocket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) explode(':', stream_socket_get_name($deadSocket, false))[1];
    fclose($deadSocket);

    expect(fn () => TailcoveHandler::send("http://127.0.0.1:{$port}/api/v1/logs", null, [['message' => 'x']], 0.5))
        ->toThrow(RuntimeException::class);
});

it('never lets a delivery failure escape flush()', function () {
    $deadSocket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) explode(':', stream_socket_get_name($deadSocket, false))[1];
    fclose($deadSocket);

    $handler = new TailcoveHandler(
        endpoint: "http://127.0.0.1:{$port}/api/v1/logs",
        token: null,
        batchSize: 50,
        batchSeconds: 100,
        dispatch: 'shutdown',
        queueConnection: null,
        httpTimeout: 0.5,
    );

    $handler->handle(record('will fail to send'));

    expect(fn () => $handler->flush())->not->toThrow(Throwable::class);
});

it('classifies a curl error as a delivery failure', function () {
    expect(fn () => TailcoveHandler::assertDelivered(CURLE_COULDNT_CONNECT, 'Failed to connect', 0))
        ->toThrow(RuntimeException::class, 'Failed to connect');
});

it('classifies a non-2xx response as a delivery failure', function () {
    expect(fn () => TailcoveHandler::assertDelivered(0, '', 500))
        ->toThrow(RuntimeException::class, '500');
});

it('treats a 2xx response as delivered', function () {
    TailcoveHandler::assertDelivered(0, '', 202);
})->throwsNoExceptions();
