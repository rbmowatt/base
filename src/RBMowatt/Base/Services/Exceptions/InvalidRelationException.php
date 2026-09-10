<?php  namespace RBMowatt\Base\Services\Exceptions;

use RBMowatt\Base\Exception;

/**
* The model is kept on the exception but out of the message, for the same reason
* as InvalidQueryParamException: this message reaches the client.
*/
class InvalidRelationException extends Exception
{
    protected $model;

    protected $badData;

    public function __construct($model, $badData = [],  $message = "Relation Does Not Exist", $code = 0) {
        $this->model = $model;
        $this->badData = $badData;
        $message .= " :relations=>[" . implode(',', $badData) . "]" ;
        parent::__construct($message, $code);
    }

    public function getModel()
    {
        return $this->model;
    }

    /**
    * @return array<int, mixed>
    */
    public function getBadData()
    {
        return $this->badData;
    }
}
