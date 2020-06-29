<?php  namespace RBMowatt\Base\Services\Exceptions;
use RBMowatt\Base\Exception;

class InvalidRelationException extends Exception
{
    public function __construct($model, $badData = [],  $message = "Relation Does Not Exist", $code = 0) {
         $message .= " :model=>[" . get_class($model) . "]" . " :relations=>[" . implode(',', $badData) . "]" ;
         return parent::__construct($message, $code);
     }
}
