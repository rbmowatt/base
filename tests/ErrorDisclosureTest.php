<?php

namespace RBMowatt\BaseTests;

use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use RBMowatt\Base\Exception as BaseException;
use RBMowatt\Base\Rest\ApiResponse;
use RBMowatt\Base\Services\Exceptions\InvalidQueryParamException;
use RBMowatt\Base\Services\Exceptions\InvalidRelationException;

class ErrorDisclosureTest extends TestCase
{
    private function body(Exception $e): array
    {
        // $log = false so the test does not need a log target
        return json_decode((new ApiResponse())->exception($e, 500, false)->getContent(), true);
    }

    public function testAQueryExceptionNeverReachesTheClient(): void
    {
        Config::set('app.debug', false);

        $sql = 'select "accounts"."password_hash" from "accounts" where "id" = 1';
        $e = new QueryException('testing', $sql, [], new Exception('no such column: accounts.password_hash'));

        $body = $this->body($e);

        $this->assertSame(ApiResponse::REDACTED_MESSAGE, $body['error']);
        $this->assertStringNotContainsString('select', $body['error']);
        $this->assertStringNotContainsString('password_hash', $body['error']);
        $this->assertStringNotContainsString('accounts', $body['error']);
    }

    public function testThePackagesOwnExceptionsStillExplainThemselves(): void
    {
        Config::set('app.debug', false);

        $body = $this->body(new BaseException('Request Resulted In Zero Results'));

        $this->assertSame('Request Resulted In Zero Results', $body['error']);
    }

    public function testAnUnknownFilterKeyDoesNotNameTheServiceOrModel(): void
    {
        Config::set('app.debug', false);

        $service = new class {};
        $model = new class {};

        $body = $this->body(new InvalidQueryParamException($service, $model, ['zzz']));

        $this->assertStringContainsString('zzz', $body['error']);
        $this->assertStringNotContainsString('class@anonymous', $body['error']);
        $this->assertStringNotContainsString(':service', $body['error']);
        $this->assertStringNotContainsString(':model', $body['error']);
    }

    public function testAnUnknownRelationDoesNotNameTheModel(): void
    {
        Config::set('app.debug', false);

        $body = $this->body(new InvalidRelationException(new class {}, ['nope']));

        $this->assertStringContainsString('nope', $body['error']);
        $this->assertStringNotContainsString('class@anonymous', $body['error']);
        $this->assertStringNotContainsString(':model', $body['error']);
    }

    public function testTheOffendingObjectsAreStillReachableForLogging(): void
    {
        $service = new class {};
        $model = new class {};

        $e = new InvalidQueryParamException($service, $model, ['zzz']);

        $this->assertSame($service, $e->getService());
        $this->assertSame($model, $e->getModel());
        $this->assertSame(['zzz'], $e->getBadData());
    }

    public function testDebugModeStillShowsEverything(): void
    {
        Config::set('app.debug', true);

        $body = $this->body(new Exception('boom'));

        $this->assertStringContainsString('boom', $body['error']);
        $this->assertStringContainsString('FILE::', $body['error']);
    }

    public function testEveryResponseCarriesACorrelationId(): void
    {
        Config::set('app.debug', false);

        $body = $this->body(new Exception('boom'));

        // the redacted body is only useful if it can be tied back to the log line
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $body['responseId']);
    }
}
