<?php

namespace RBMowatt\Utilities\Rest\Exceptions;

use RBMowatt\Base\Exception;

/**
* Description of ValidationException
*
* @author rmowatt
*/
class MissingParameterException extends Exception
{
    public function __construct($param, $code = 0, Exception $previous = null)
    {
        parent::__construct('Missing Required Parameter [ ' . $param . ' ]' , $code, $previous);
    }
}
