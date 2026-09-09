<?php

namespace RBMowatt\BaseTests;

use RBMowatt\Base\ErrorCodes;
use RBMowatt\Base\Exception as BaseException;
use RBMowatt\Base\Requests\BaseFormRequest;

class DenyingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [];
    }
}

class AllowingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}

class BaseFormRequestTest extends TestCase
{
    public function testFailedAuthorizationStopsTheRequest(): void
    {
        $request = $this->resolve(DenyingRequest::class);

        try {
            $request->validateResolved();
            $this->fail('authorize() returned false but the request was allowed through');
        } catch (BaseException $e) {
            $this->assertSame(ErrorCodes::USER_LACKS_PERMISSION, $e->getCode());
        }
    }

    public function testPassingAuthorizationStillResolves(): void
    {
        $request = $this->resolve(AllowingRequest::class);

        $request->validateResolved();

        $this->assertSame([], $request->validated());
    }

    private function resolve(string $class): BaseFormRequest
    {
        $request = $class::createFrom($this->app['request'], new $class());

        return $request->setContainer($this->app);
    }
}
