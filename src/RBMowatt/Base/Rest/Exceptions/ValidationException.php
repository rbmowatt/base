<?php

namespace RBMowatt\Utilities\Rest\Exceptions;

use RBMowatt\Base\Exception;

/**
* Description of ValidationException
*
* @author rmowatt
*/
class ValidationException extends Exception
{

    public function __construct($validator, $code = 0,
    Exception $previous = null)
    {
        parent::__construct('Input Validation Failed', $code, $previous);
        $this->validator = $validator;
    }

    // custom string representation of object
    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

    public function getValidator()
    {
        return $this->validator;
    }

}
