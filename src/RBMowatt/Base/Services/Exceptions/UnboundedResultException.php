<?php  namespace RBMowatt\Base\Services\Exceptions;

use RBMowatt\Base\Exception;

/**
* Raised when BaseService::all() finds more rows than the service allows.
*
* The model is kept off the message for the same reason as the other query
* exceptions: ApiResponse echoes this package's messages back to the client with
* debug off, and the class name is not the client's business.
*/
class UnboundedResultException extends Exception
{
    protected $model;

    protected $ceiling;

    public function __construct($model, $ceiling, $message = null, $code = 0) {
        $this->model = $model;
        $this->ceiling = $ceiling;
        $message = $message ?: sprintf(
            'More than %d rows match. Use a paginated request, or narrow the filters.',
            $ceiling
        );
        parent::__construct($message, $code);
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getCeiling()
    {
        return $this->ceiling;
    }
}
