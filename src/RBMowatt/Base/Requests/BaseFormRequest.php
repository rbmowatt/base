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

    /**
    * The full signature has to be carried through. Symfony's Request::create() builds
    * the instance as
    * new static($query, $request, $attributes, $cookies, $files, $server, $content),
    * so a no-argument constructor accepts all seven and throws them away, leaving a
    * request whose all() is empty and every rule reporting "field is required".
    * Laravel's own resolution path masks that, because FormRequestServiceProvider
    * constructs with no arguments and then copies the real request in via
    * createFrom().
    *
    * @param array<string, mixed> $query
    * @param array<string, mixed> $request
    * @param array<string, mixed> $attributes
    * @param array<string, mixed> $cookies
    * @param array<string, mixed> $files
    * @param array<string, mixed> $server
    * @param string|resource|null $content
    */
    public function __construct(
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        $content = null
    ) {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
        $this->queryParser = App::make(QueryParser::class);
    }

    /**
    * Laravel calls this when authorize() returns false and expects it to throw. An
    * empty body here swallows the rejection and lets the request carry on into
    * validation and the controller action.
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
