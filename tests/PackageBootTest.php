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

    public function testNoHelpersLeakIntoTheGlobalNamespace(): void
    {
        foreach (['vdd', 'ldd', 'qLog', 'evalTruth', 'getVersion', 'getRootPath'] as $helper) {
            $this->assertFalse(function_exists($helper), $helper . '() must not be declared by this package');
        }

        $this->assertFalse(defined('STANDARD_DATE_FORMAT'));
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
