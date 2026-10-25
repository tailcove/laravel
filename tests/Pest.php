<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Monolog\Level;
use Monolog\LogRecord;
use Tailcove\Laravel\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind different classes or traits.
|
*/

uses(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

uses()->beforeEach(function () {
    $this->socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) explode(':', stream_socket_get_name($this->socket, false))[1];
    $this->endpoint = "http://127.0.0.1:{$port}/api/v1/logs";
})->afterEach(function () {
    if (is_resource($this->socket)) {
        fclose($this->socket);
    }
})->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A raw TCP listener standing in for the ingest endpoint: good enough to
 * capture the exact bytes TailcoveHandler sends without needing a real
 * HTTP server. Pass a short $timeout to assert that nothing was sent (e.g.
 * the handler is disabled) without slowing the suite down.
 *
 * @param  resource  $socket
 */
function acceptOneRequest($socket, float $timeout = 2.0): string
{
    $connection = @stream_socket_accept($socket, $timeout);

    if ($connection === false) {
        return '';
    }

    stream_set_timeout($connection, 1);
    $request = '';

    while (! feof($connection)) {
        $chunk = fread($connection, 8192);

        if ($chunk === false || $chunk === '') {
            break;
        }

        $request .= $chunk;

        // A Content-Length-terminated body never triggers feof() on its
        // own since the client doesn't close the connection after POSTing
        // — stop once we've clearly seen the JSON body's closing bracket.
        if (str_contains($request, "\r\n\r\n[") && str_ends_with(rtrim($request), ']')) {
            break;
        }
    }

    fclose($connection);

    return $request;
}

function record(string $message, array $context = []): LogRecord
{
    return new LogRecord(
        datetime: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        channel: 'test',
        level: Level::Error,
        message: $message,
        context: $context,
    );
}
