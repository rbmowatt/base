<?php
namespace RBMowatt\Base\Rest\Interfaces;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Validator;
use RBMowatt\Base\Exception as BaseException;

/**
 * The response-building contract BaseApiController depends on.
 *
 * Envelope fields are set through __get/__set on the implementation rather than
 * named methods, so they are not part of this interface; the methods here are the
 * ones that build or render a response.
 */
interface ApiResponseInterface
{
    /**
     * @param mixed $data
     * @param int $code
     * @return JsonResponse
     */
    public function ok($data, $code = 200);

    /**
     * @param mixed $error
     * @param bool $success
     * @param mixed $data
     * @param int $statusCode
     * @return JsonResponse
     */
    public function error($error, $success = false, $data = [], $statusCode = 400);

    /**
     * @param int $code
     * @param bool $log
     * @param string $logLevel
     * @return JsonResponse
     */
    public function exception(Exception $e, $code = 500, $log = true, $logLevel = BaseException::ERROR);

    /**
     * @param Exception $e
     * @return JsonResponse
     */
    public function validationError($e, Validator $validator);

    /**
     * @return string
     */
    public function formatException(Exception $e);

    /**
     * @param mixed $data
     * @return static
     */
    public function setData($data);

    /**
     * @param array $meta
     * @return static
     */
    public function setMeta($meta);

    /**
     * @param int $eCode
     * @return static
     */
    public function setErrorCode($eCode);

    /**
     * @param array $headers
     * @return static
     */
    public function withHeaders(array $headers);

    /**
     * @return int
     */
    public function getStatusCode();

    /**
     * @param int $code
     * @return static
     * @throws \InvalidArgumentException when the code is outside 100-599
     */
    public function setStatusCode($code);

    /**
     * @return JsonResponse
     */
    public function toJson();

    /**
     * @return JsonResponse
     */
    public function json();

    /**
     * @return array
     */
    public function toArray();
}
