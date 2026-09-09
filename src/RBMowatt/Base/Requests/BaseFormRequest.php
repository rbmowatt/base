<?php
namespace RBMowatt\Base\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\App;
use RBMowatt\Base\ErrorCodes;
use RBMowatt\Base\Exception as BaseException;
use RBMowatt\Base\Rest\Exceptions\ValidationException;
use RBMowatt\Base\Rest\Query\QueryParser;

class BaseFormRequest extends FormRequest
{
    protected $queryParser;

    public function __construct()
    {
        parent::__construct();
        $this->queryParser = App::make(QueryParser::class);
    }

    /**
    * Laravel calls this when authorize() returns false, and expects it to throw.
    * This used to be an empty body, so a failing authorize() was swallowed and the
    * request carried on into validation and the controller action.
    */
    protected function failedAuthorization(): void
    {
        $this->throwPermissionsException('This action is unauthorized.');
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->checkPermissions($validator);
        });
    }

    protected function checkPermissions($validator)
    {

    }

    protected function throwPermissionsException($message, $code = ErrorCodes::USER_LACKS_PERMISSION)
    {
        throw new BaseException($message, $code);
    }
}
