<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Tailcove\Laravel\ContextProcessor;
use Tailcove\Laravel\Support\RequestId;

it('adds environment, hostname, and request id to every record', function () {
    app()->instance(RequestId::class, new RequestId('req-123'));

    $processor = new ContextProcessor(app());

    $enriched = $processor(record('hello'));

    expect($enriched->extra)
        ->toHaveKey('environment', app()->environment())
        ->toHaveKey('hostname')
        ->toHaveKey('request_id', 'req-123');
});

it('adds the authed user id when a user is logged in', function () {
    $user = new class implements Authenticatable
    {
        public function getAuthIdentifierName()
        {
            return 'id';
        }

        public function getAuthIdentifier()
        {
            return 99;
        }

        public function getAuthPasswordName()
        {
            return 'password';
        }

        public function getAuthPassword()
        {
            return 'secret';
        }

        public function getRememberToken()
        {
            return null;
        }

        public function setRememberToken($value) {}

        public function getRememberTokenName()
        {
            return 'remember_token';
        }
    };

    test()->actingAs($user);

    $processor = new ContextProcessor(app());

    $enriched = $processor(record('hello'));

    expect($enriched->extra)->toHaveKey('user_id', 99);
});

it('does not add a user id when nobody is logged in', function () {
    $processor = new ContextProcessor(app());

    $enriched = $processor(record('hello'));

    expect($enriched->extra)->not->toHaveKey('user_id');
});

it('renders an exception context key as structured data', function () {
    $processor = new ContextProcessor(app(), exceptionTraceLimit: 3);

    $exception = new RuntimeException('kaboom');
    $enriched = $processor(record('an error happened', ['exception' => $exception]));

    expect($enriched->context['exception'])
        ->toMatchArray([
            'class' => RuntimeException::class,
            'message' => 'kaboom',
        ])
        ->and($enriched->context['exception'])->toHaveKey('location')
        ->and($enriched->context['exception']['trace'])->toBeArray();
});

it('falls back to the original record instead of throwing when enrichment fails', function () {
    // Binding a non-object under RequestId::class makes ->toString() blow
    // up inside the processor; it must swallow that and hand back the
    // record untouched rather than let it propagate into the caller's
    // Log:: call.
    app()->instance(RequestId::class, 'not-a-request-id');

    $processor = new ContextProcessor(app());

    $original = record('hello');

    expect(fn () => $processor($original))->not->toThrow(Throwable::class);
});
