<?php  namespace RBMowatt\Base\Services\Exceptions;

use RBMowatt\Base\Exception;

/**
* The service and model arguments are kept in the signature and on the exception
* so a handler or log formatter can still reach them, but they are no longer
* rendered into the message: ApiResponse echoes this package's own messages back
* to the client with debug off, and ":service=>[App\Services\PatientService]"
* handed the internal namespace to anyone who sent an unknown query parameter.
*/
class InvalidQueryParamException extends Exception
{
    protected $service;

    protected $model;

    protected $badData;

    public function __construct($service, $model, $badData = [],  $message = "Scope Mapping Does Not Exist", $code = 0) {
        $this->service = $service;
        $this->model = $model;
        $this->badData = $badData;
        $message .= " :scope=>[" . implode(',', $badData) . "]" ;
        parent::__construct($message, $code);
    }

    public function getService()
    {
        return $this->service;
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
