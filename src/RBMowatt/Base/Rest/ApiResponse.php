<?php

namespace RBMowatt\Base\Rest;

use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use RBMowatt\Base\ErrorCodes;
use RBMowatt\Base\Exception as BaseException;
use RBMowatt\Base\Rest\Exceptions\ValidationException;

/**
* Builds the standard API envelope and renders it as a JsonResponse.
*
* This composes a JsonResponse rather than extending Symfony's Response: Response
* declares setStatusCode(int $code, ?string $text = null): static, so the loose
* single-argument override this class needs is a fatal signature conflict against
* any Symfony 6+.
*/
class ApiResponse
{
    protected $_contents = array();

    protected $statusCode = 200;

    protected $headers = array();

    const FAILED_VALIDATION_CODE = 422;

    public function __construct($content = '', $status = 200, $headers = array())
    {
        $this->statusCode = (int) $status;
        $this->headers = $headers;
        $this->_contents = array(
            'success'=>false,
            'href' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI']: 'N/A',
            'app' => Config::get('app.name', '[app.name] not set '),
            'uid'=>  NULL,
            'time' => date('y-m-d H:i:s'),
            'statusCode' => $status,
            // 32 hex chars, same shape md5(time()) produced, so log greps keep working.
            // md5(time()) handed every response in the same second an identical id,
            // which made responseId useless for correlating a request with its logs.
            'responseId' => bin2hex(random_bytes(16)),
            'error' => NULL,
            'errorCode'=>NULL,
            // app.version is not a Laravel default. The getVersion() branch is only
            // for apps still declaring the helper this package used to autoload.
            'version'=> Config::get('app.version') ?? (function_exists('getVersion') ? getVersion() : 'undefined')
        );
        if ($content !== '' && $content !== null) {
            $this->_contents['data'] = $content;
        }
    }

    /**
    * Return a new instance of self
    *
    * @return self
    */
    public static function make()
    {
        return new self;
    }

    /**
    * Manually create the object
    *
    * @param array $content
    * @param int $statusCode
    * @param array $headers
    * @return self
    */
    public static function create($content = array(), $statusCode = 200, $headers = array())
    {
        return new self($content, $statusCode, $headers);
    }

    /**
    * Everything went well
    *
    * @param mixed $data
    * @param int $code
    * @return JsonResponse
    */
    public function ok($data, $code = 200)
    {
        $this->setStatusCode($code);
        $this->success = true;
        $this->data = $data;
        return $this->toJson();
    }
    /**
     * Add/Set the Metadata in the response
     * @param array $meta
     * @return self
     */
    public function setMeta($meta)
    {
        $this->_contents['meta'] = $meta;
        return $this;
    }
    /**
    * @param Exception $e
    * @param Validator $validator
    * @return JsonResponse
    */
    public function validationError($e, Validator $validator)
    {
        $this->success = false;
        $this->validationErrors = $validator->getMessageBag();
        return $this->error($e->getMessage(), false, [], self::FAILED_VALIDATION_CODE);
    }
    /**
     * Set a proprietary error code on the errorCode property
     * (not to be confused with http status code)
     * This allows you to send alomng a more specific code to be translated by the user
     * @param int $eCode
     * @return self
     */

    public function setErrorCode($eCode)
    {
      $this->errorCode = $eCode;
      return $this;
    }
    /**
    * Theres been an error but we dont want to send back a 500
    *
    * Defaults to 400, not 200: a 200 carrying an error body makes every caller
    * parse the envelope to find out the call failed, and defeats retry and
    * alerting rules that key off the status code.
    *
    * @param mixed $error
    * @param bool $success
    * @param mixed $data
    * @param int $statusCode
    * @return JsonResponse
    */
    public function error($error, $success = false, $data = [], $statusCode = 400)
    {
        $this->error = $error;
        $this->success = (bool) $success;
        $this->data = $data;
        $this->setStatusCode($statusCode);
        return $this->toJson();
    }
    /**
    * ut oh
    *
    * @param Exception $e
    * @param int $code
    * @param bool $log
    * @return JsonResponse
    */
    public function exception(Exception $e, $code = 500, $log = true, $logLevel = BaseException::ERROR )
    {
        $log ? Log::$logLevel($e) : null;
        $this->setStatusCode($code);
        $this->errorCode = ($e instanceof BaseException) ? $e->getCode() : ErrorCodes::NO_IDEA;
        if ($e instanceof ValidationException)
        {
            return $this->validationError($e, $e->getValidator());
        }
        if($e instanceof QueryException)
        {
            $this->errorCode = ErrorCodes::GENERAL_DATABASE_EXCEPTION;
        }
        $this->error = $this->formatException($e);
        return $this->toJson();
    }
    /**
    * Render an exception for the `error` field.
    *
    * Reads app.debug rather than env('APP_ENV'): once the host app runs
    * config:cache, env() outside of config files returns null, so an
    * env-based check silently picks the wrong branch in production.
    *
    * @param Exception $e
    * @return string
    */
    public function formatException(Exception $e)
    {
        if (!Config::get('app.debug', false))
        {
            return $e->getMessage();
        }
        return $e->getMessage() . ', FILE:: ' . $e->getFile() . ', LINE:: ' . $e->getLine();
    }
    /**
    * Set the data manually
    *
    * @param mixed $data
    * @return self
    */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }
    /**
    * Just a different name for toJson
    *
    * @return JsonResponse
    */
    public function json()
    {
        return $this->toJson();
    }
    /**
    * Get the response as a response object
    *
    * @return JsonResponse
    */
    public function toJson()
    {
        $this->setUser();
        $this->_contents['statusCode'] = $this->getStatusCode();
        // No JSON_NUMERIC_CHECK: it coerced every numeric-looking string in the
        // payload, so "07005" shipped as 7005, "1.10" as 1.1, and ids past
        // 2^53 landed outside what a JS client can parse back without loss.
        // CORS is the host app's HandleCors middleware to set. Sending
        // Access-Control-Allow-Origin from here overrode whatever it configured.
        return new JsonResponse($this->_contents, $this->getStatusCode(), $this->headers);
    }
    /**
    * Turn the response to an array instead of json
    *
    * @return array
    */
    public function toArray()
    {
        $this->setUser();
        $this->_contents['statusCode'] = $this->getStatusCode();
        return $this->_contents;
    }
    /**
     * Set the user data in the response if available
     */
    protected function setUser()
    {
        // illuminate/auth is a suggest, not a require. Without the binding the
        // facade raises "Target class [auth] does not exist" and takes down every
        // response, not just the authenticated ones. Auth::id() also avoids
        // assuming the user model exposes an `id` property.
        $this->_contents['uid'] = App::bound('auth') ? Auth::id() : null;
    }

    /**
    * Get the current HTTP status code
    *
    * @return int
    */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
    * Sets the response status code.
    *
    * @param int   $code HTTP status code
    * @return self
    *
    * @throws InvalidArgumentException When the HTTP status code is not valid
    */
    public function setStatusCode($code)
    {
        $code = (int) $code;
        if ($code < 100 || $code >= 600)
        {
            throw new InvalidArgumentException(sprintf('The HTTP status code "%s" is not valid.', $code));
        }
        $this->statusCode = $code;
        $this->_contents['statusCode'] = $code;
        return $this;
    }

    /**
    * Add headers that will be attached to the rendered JsonResponse
    *
    * @param array $headers
    * @return self
    */
    public function withHeaders(array $headers)
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
    * Its magical
    *
    * @param string $key
    * @param mixed $value
    */
    public function __set($key, $value)
    {
        $this->_contents[$key] = $value;
    }

    /**
    * Its magical
    *
    * @param string $key
    */
    public function __get($key)
    {
        return array_key_exists($key, $this->_contents) ? $this->_contents[$key] : null;
    }

    /**
    * @param string $key
    */
    public function __isset($key)
    {
        return isset($this->_contents[$key]);
    }
}
