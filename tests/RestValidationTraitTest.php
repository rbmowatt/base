<?php

namespace RBMowatt\BaseTests;

use Illuminate\Http\Request;
use RBMowatt\Base\Controllers\Api\BaseApiController;
use RBMowatt\Base\Rest\Exceptions\ValidationException;
use RBMowatt\Base\Rest\Traits\RestValidationTrait;

/**
 * Deliberately not a controller: the trait used to read self::REQUIRED, which
 * only existed on BaseApiController.
 */
class StandaloneValidator
{
    use RestValidationTrait;

    public function check(array $rules, array $input)
    {
        $this->justify($rules, Request::create('/things', 'POST', $input));
    }

    public function parse(array $rules)
    {
        return $this->parseValidation($rules);
    }
}

class RestValidationTraitTest extends TestCase
{
    public function testTheTraitCarriesItsOwnRequiredConstant(): void
    {
        $this->assertSame('required', StandaloneValidator::REQUIRED);
        $this->assertSame('required', BaseApiController::REQUIRED);
    }

    public function testUsableOutsideAController(): void
    {
        $validator = new StandaloneValidator();

        $validator->check([StandaloneValidator::REQUIRED => ['name']], ['name' => 'a widget']);

        $this->expectException(ValidationException::class);
        $validator->check([StandaloneValidator::REQUIRED => ['name']], []);
    }

    public function testRequiredKeysMergeWithTheirExtraRules(): void
    {
        $parsed = (new StandaloneValidator())->parse([
            StandaloneValidator::REQUIRED => ['name', 'age|integer|min:1'],
            'nickname' => 'string',
        ]);

        $this->assertSame([
            'name' => 'required',
            'age' => 'required|integer|min:1',
            'nickname' => 'string',
        ], $parsed);
    }
}
