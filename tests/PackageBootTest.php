<?php

namespace RBMowatt\BaseTests;

use RBMowatt\Base\BaseServiceProvider;
use RBMowatt\Base\Rest\ApiResponse;

class PackageBootTest extends TestCase
{
    public function testProviderIsRegistered(): void
    {
        $this->assertNotNull($this->app->getProvider(BaseServiceProvider::class));
    }

    public function testHelperFunctionsAreAutoloaded(): void
    {
        $this->assertTrue(function_exists('evalTruth'));
        $this->assertTrue(function_exists('getVersion'));
        $this->assertTrue(defined('STANDARD_DATE_FORMAT'));
    }

    public function testEvalTruthOnlyAcceptsOneAndTrue(): void
    {
        $this->assertTrue(evalTruth('1'));
        $this->assertTrue(evalTruth('true'));
        $this->assertFalse(evalTruth('yes'));
        $this->assertFalse(evalTruth('TRUE'));
    }

    public function testApiResponseRendersAnEnvelope(): void
    {
        $response = (new ApiResponse())->ok(['id' => 7]);

        $this->assertSame(200, $response->getStatusCode());

        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertSame(['id' => 7], $payload['data']);
        $this->assertArrayHasKey('responseId', $payload);
    }
}
