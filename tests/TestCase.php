<?php

namespace RBMowatt\BaseTests;

use Orchestra\Testbench\TestCase as TestbenchTestCase;
use RBMowatt\Base\BaseServiceProvider;

abstract class TestCase extends TestbenchTestCase
{
    protected function getPackageProviders($app)
    {
        return [BaseServiceProvider::class];
    }
}
