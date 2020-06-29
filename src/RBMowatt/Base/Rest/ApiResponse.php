<?php

namespace RBMowatt\Base\Rest;

use Auth;
use Config;
use Exception;
use InvalidArgumentException;
use Log;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Validation\Validator;
use RBMowatt\Base\Exception as BaseException;
use RBMowatt\Base\ErrorCodes;
use RBMowatt\Base\Rest\Exceptions\ValidationException;


Class ApiResponse extends Response //implements ApiResponseInterface
{
    protected $_contents = array();

    const FAILED_VALIDATION_CODE = 422;

    public function __construct($content = '', $status = 200, $headers = array())
    {
        parent::__construct($content, $status, $headers);
        $this->_contents = array(
            'success'=>false,
            'href' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI']: 'N/A',
            'app' => Config::get('app.name', '[app.name] not set '),
            'uid'=>  NULL,
            'time' => date('y-m-d H:i:s'),
            'statusCode' => $status,
            'responseId' => md5(time()),
            'error' => NULL,
            'errorCode'=>NULL,
            'version'=> function_exists('getVersion') ? getVersion() : 'undefined'
        );
    }

    /**
    * Return a new instance of self
    *
    * @return self
    */
    public function make()
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
    * @param mixed $error
    * @param bool $success
    * @param mixed $data
    * @param int $statusCode
    * @return JsonResponse
    */
    public function error($error, $success = false, $data = [], $statusCode = 200)
    {
        $this->error = $error;
        $this->success = ($success) ? 'true' : false;
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
    * Let's make this pretty
    *
    * @param Exception $e
    * @return string
    */
    public function formatException(Exception $e)
    {
        return (env('APP_ENV')!== 'production') ? $e->getMessage() : $e->getMessage() . ', FILE:: ' . $e->getFile() . ', LINE:: ' . $e->getLine();
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
    * @return string
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
        $jr = new JsonResponse($this->_contents, $this->getStatusCode(), $this->headers->all(),JSON_NUMERIC_CHECK);
        return $jr->withHeaders(['Access-Control-Allow-Origin'=>'*',
        'Access-Control-Allow-Methods'=>'GET, POST, PUT, DELETE, OPTIONS']);
    }
    /**
    * Turn the response to an array instead of json
    *
    * @return array
    */
    public function toArray()
    {
        $this->setUser();
        $result = array();
        foreach ($this as $key => $value)
        {
            $result[$key] = $value;
        }
        return $result;
    }
    /**
     * Set the user data in the response if available
     */
    protected function setUser()
    {
        $this->_contents['uid'] = Auth::check() ? Auth::user()->id : null;
    }

    /**
    * Sets the response status code.
    *
    * @param int   $code HTTP status code
    * @return Response
    *
    * @throws InvalidArgumentException When the HTTP status code is not valid
    */
    public function setStatusCode($code)
    {
        $this->statusCode = $code = (int) $code;
        $this->_contents['statusCode'] = $code = (int) $code;
        if ($this->isInvalid())
        {
            throw new InvalidArgumentException(sprintf('The HTTP status code "%s" is not valid.', $code));
        }
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
        $this->setContent(json_encode($this->_contents));
    }

    /**
    * Its magical
    *
    * @param string $key
    */
    public function &__get($key)
    {
        return $this->_contents[$key];
    }

}
