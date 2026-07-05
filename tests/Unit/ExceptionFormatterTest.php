<?php

use Tailcove\Laravel\Support\ExceptionFormatter;

it('renders class, message, file:line, and a trimmed trace', function () {
    $exception = new RuntimeException('something broke');

    $formatted = ExceptionFormatter::format($exception, 10);

    expect($formatted['class'])->toBe(RuntimeException::class)
        ->and($formatted['message'])->toBe('something broke')
        ->and($formatted['location'])->toBe("{$exception->getFile()}:{$exception->getLine()}")
        ->and($formatted['trace'])->toBeArray();
});

it('trims the trace to the configured limit', function () {
    $exception = new RuntimeException('deep');

    $formatted = ExceptionFormatter::format($exception, 1);

    expect(count($formatted['trace']))->toBeLessThanOrEqual(1);
});
