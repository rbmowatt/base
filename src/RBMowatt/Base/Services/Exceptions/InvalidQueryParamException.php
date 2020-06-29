<?php  namespace RBMowatt\Base\Services\Exceptions;

use RBMowatt\Base\Exception;

class InvalidQueryParamException extends Exception
{
    public function __construct($service, $model, $badData = [],  $message = "Scope Mapping Does Not Exist", $code = 0) {
        $message .= " :service=>[" . get_class($service) . "]" . " :model=>[" . get_class($model) . "]" . " :scope=>[" . implode(',', $badData) . "]" ;
        return parent::__construct($message, $code);
    }
}
