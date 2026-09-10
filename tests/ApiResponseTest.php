<?php

namespace RBMowatt\BaseTests;

use Exception;
use Illuminate\Http\Request as HttpRequest;
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

    public function testDebugOffRedactsAForeignException(): void
    {
        Config::set('app.debug', false);

        $formatted = (new ApiResponse())->formatException(new Exception('boom'));

        $this->assertSame(ApiResponse::REDACTED_MESSAGE, $formatted);
        $this->assertStringNotContainsString('boom', $formatted);
    }

    public function testUidIsNullWithoutAnAuthBinding(): void
    {
        $this->app->offsetUnset('auth');

        $this->assertNull((new ApiResponse())->ok([])->getData(true)['uid']);
    }

    public function testUidComesFromTheAuthenticatedUser(): void
    {
        $user = new \Illuminate\Foundation\Auth\User();
        $user->id = 42;
        $this->actingAs($user);

        $this->assertSame(42, (new ApiResponse())->ok([])->getData(true)['uid']);
    }

    public function testMakeIsCallableStatically(): void
    {
        $this->assertInstanceOf(ApiResponse::class, ApiResponse::make());
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

        $this->assertSame(ApiResponse::REDACTED_MESSAGE, $payload['error']);
        $this->assertStringNotContainsString('boom', $payload['error']);
        $this->assertSame(ErrorCodes::NO_IDEA, $payload['errorCode']);
        $this->assertSame(500, $payload['statusCode']);
    }

    public function testHrefComesFromTheBoundRequest(): void
    {
        $this->app->instance('request', HttpRequest::create('/v1/widgets?limit=5'));

        $this->assertSame('/v1/widgets?limit=5', (new ApiResponse())->ok([])->getData(true)['href']);
    }

    public function testHrefFollowsTheCurrentRequestNotTheProcess(): void
    {
        $this->app->instance('request', HttpRequest::create('/v1/widgets'));
        $first = (new ApiResponse())->ok([])->getData(true)['href'];

        $this->app->instance('request', HttpRequest::create('/v1/gadgets?page=2'));
        $second = (new ApiResponse())->ok([])->getData(true)['href'];

        $this->assertSame('/v1/widgets', $first);
        $this->assertSame('/v1/gadgets?page=2', $second);
    }

    public function testHrefIsNullWithNoRequestBound(): void
    {
        $this->app->offsetUnset('auth');
        $this->app->offsetUnset('request');

        $this->assertNull((new ApiResponse())->ok([])->getData(true)['href']);
    }

    public function testTimeIsIso8601WithAnOffset(): void
    {
        $time = (new ApiResponse())->ok([])->getData(true)['time'];

        $this->assertNotFalse(\DateTimeImmutable::createFromFormat(DATE_ATOM, $time));
    }

    public function testNoCorsHeadersAreSet(): void
    {
        $headers = (new ApiResponse())->ok([])->headers;

        $this->assertFalse($headers->has('Access-Control-Allow-Origin'));
        $this->assertFalse($headers->has('Access-Control-Allow-Methods'));
    }

    public function testExplicitHeadersStillReachTheResponse(): void
    {
        $headers = (new ApiResponse())
            ->withHeaders(['X-Request-Source' => 'test'])
            ->ok([])
            ->headers;

        $this->assertSame('test', $headers->get('X-Request-Source'));
    }

    public function testVersionComesFromAppConfig(): void
    {
        Config::set('app.version', '2.4.1');

        $this->assertSame('2.4.1', (new ApiResponse())->ok([])->getData(true)['version']);
    }

    public function testVersionFallsBackToUndefined(): void
    {
        Config::set('app.version', null);

        $this->assertSame('undefined', (new ApiResponse())->ok([])->getData(true)['version']);
    }
}
