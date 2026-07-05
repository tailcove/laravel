<?php

namespace Tailcove\Laravel\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Tailcove\Laravel\TailcoveServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [TailcoveServiceProvider::class];
    }
}
