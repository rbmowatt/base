<?php

namespace RBMowatt\Base\Rest\Exceptions;

use RBMowatt\Base\Exception;
use Throwable;

/**
* Description of MissingParameterException
*
* @author rmowatt
*/
class MissingParameterException extends Exception
{
    public function __construct($param, $code = 0, ?Throwable $previous = null)
    {
        parent::__construct('Missing Required Parameter [ ' . $param . ' ]' , $code, $previous);
    }
}
