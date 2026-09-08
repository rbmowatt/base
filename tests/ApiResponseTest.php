<?php

namespace RBMowatt\BaseTests;

use Exception;
use Illuminate\Support\Facades\Config;
use RBMowatt\Base\ErrorCodes;
use RBMowatt\Base\Rest\ApiResponse;

class ApiResponseTest extends TestCase
{
    public function testDebugModeIncludesFileAndLine(): void
    {
        Config::set('app.debug', true);

        $formatted = (new ApiResponse())->formatException(new Exception('boom'));

        $this->assertStringContainsString('boom', $formatted);
        $this->assertStringContainsString('FILE::', $formatted);
        $this->assertStringContainsString('LINE::', $formatted);
    }

    public function testDebugOffLeaksNeitherFileNorLine(): void
    {
        Config::set('app.debug', false);

        $formatted = (new ApiResponse())->formatException(new Exception('boom'));

        $this->assertSame('boom', $formatted);
    }

    public function testResponseIdIsUniquePerResponse(): void
    {
        $ids = [];
        for ($i = 0; $i < 50; $i++)
        {
            $ids[] = (new ApiResponse())->ok([])->getData(true)['responseId'];
        }

        $this->assertCount(50, array_unique($ids));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $ids[0]);
    }

    public function testNumericLookingStringsSurviveEncoding(): void
    {
        $payload = (new ApiResponse())->ok([
            'zip' => '07005',
            'account' => '000123',
            'version' => '1.10',
            'big_id' => '9007199254740993',
        ])->getData(true)['data'];

        $this->assertSame('07005', $payload['zip']);
        $this->assertSame('000123', $payload['account']);
        $this->assertSame('1.10', $payload['version']);
        $this->assertSame('9007199254740993', $payload['big_id']);
    }

    public function testSuccessIsAlwaysABoolean(): void
    {
        $ok = (new ApiResponse())->ok([])->getData(true);
        $failed = (new ApiResponse())->error('nope')->getData(true);
        $partial = (new ApiResponse())->error('partly', true)->getData(true);

        $this->assertTrue($ok['success']);
        $this->assertFalse($failed['success']);
        $this->assertTrue($partial['success']);
    }

    public function testErrorDefaultsToFourHundredNotTwoHundred(): void
    {
        $response = (new ApiResponse())->error('nope');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(400, $response->getData(true)['statusCode']);
    }

    public function testExceptionKeepsItsStatusCode(): void
    {
        $response = (new ApiResponse())->exception(new Exception('boom'), 503, false);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(503, $response->getData(true)['statusCode']);
    }

    public function testExceptionResponseDoesNotLeakPathsWhenDebugIsOff(): void
    {
        Config::set('app.debug', false);

        $payload = (new ApiResponse())
            ->exception(new Exception('boom'), 500, false)
            ->getData(true);

        $this->assertSame('boom', $payload['error']);
        $this->assertSame(ErrorCodes::NO_IDEA, $payload['errorCode']);
        $this->assertSame(500, $payload['statusCode']);
    }
}
