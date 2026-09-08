<?php

namespace RBMowatt\BaseTests;

use RBMowatt\Base\Services\BaseService;
use RBMowatt\Base\Services\Exceptions\InvalidWhereFormatException;
use ReflectionMethod;

class BaseServiceWhereTest extends TestCase
{
    public function testMalformedWhereNamesTheOffendingClause(): void
    {
        $service = new class extends BaseService {};

        $method = new ReflectionMethod(BaseService::class, 'setWheres');
        $method->setAccessible(true);

        $this->expectException(InvalidWhereFormatException::class);
        $this->expectExceptionMessage('["bad"]');

        $method->invoke($service, null, [['bad']]);
    }
}
