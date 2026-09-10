<?php

namespace RBMowatt\BaseTests;

use Illuminate\Support\Facades\Route;
use RBMowatt\Base\Rest\ApiResponse;
use RBMowatt\Base\Rest\Interfaces\ApiResponseInterface;

class HrefStalenessTest extends TestCase
{
    protected function defineRoutes($router)
    {
        $router->get('/probe', function () {
            return response()->json([
                'container_request_uri' => app('request')->getRequestUri(),
                'envelope_href' => app(ApiResponseInterface::class)->href,
                'fresh_envelope_href' => (new ApiResponse())->href,
            ]);
        });
    }

    public function testTheContainerRequestTracksEachRequest(): void
    {
        $first = $this->get('/probe?first=1');
        $second = $this->get('/probe?second=1');

        $this->assertSame('/probe?first=1', $first->json('container_request_uri'));
        $this->assertSame('/probe?second=1', $second->json('container_request_uri'));
    }

    public function testAFreshEnvelopeTracksEachRequest(): void
    {
        $this->get('/probe?first=1');
        $second = $this->get('/probe?second=1');

        $this->assertSame('/probe?second=1', $second->json('fresh_envelope_href'));
    }

    public function testAContainerResolvedEnvelopeTracksEachRequest(): void
    {
        $this->get('/probe?first=1');
        $second = $this->get('/probe?second=1');

        $this->assertSame('/probe?second=1', $second->json('envelope_href'));
    }
}
