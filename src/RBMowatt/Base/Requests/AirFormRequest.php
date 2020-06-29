<?php
namespace RBMowatt\Base\Requests;

use App;
use Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use RBMowatt\Acl\Exceptions\PermissionDeniedException;
use RBMowatt\Utilities\Rest\ApiResponse;
use RBMowatt\Utilities\Rest\Exceptions\ValidationException;
use RBMowatt\Utilities\Rest\Query\QueryParser;

class FormRequest extends FormRequest
{

    public function __construct()
    {
        $this->queryParser = App::make(QueryParser::class);
    }

    public function failedAuthorization()
    {
    }

    public function failedValidation( Validator $validator)
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

    protected function throwPermissionsException($message, $code = 420)
    {
        throw new PermissionDeniedException($message, $code);
    }
}
