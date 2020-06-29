<?php

namespace RBMowatt\Base\Controllers\Api;

use App;
use Auth;
use App\Http\Controllers\Controller as BaseController;
use RBMowatt\Utilities\Rest\ApiResponse;
use Illuminate\Http\Request;
use RBMowatt\Base\Exceptions\EntityDoesNotExistException;
use RBMowatt\Base\Services\ServiceResultsCollection;
use RBMowatt\Base\ErrorCodes;
use RBMowatt\Utilities\Rest\Traits\RestValidationTrait;



class BaseApiController extends BaseController
{
    use RestValidationTrait;

    const REQUIRED = 'required';

    protected $aclGuard = null;
    protected $hydratedAclGuard = null;


    public function __construct(ApiResponse $response)
    {
        $this->response = $response;
        $this->request = App::make(Request::class);
        $this->user = Auth::guard('api')->user();
    }

    public function checkEntityExists($entity)
    {
        if($entity === NULL || $entity === false || (is_array($entity) && count($entity) < 1)
        || ($entity instanceof ServiceResultsCollection && $entity->count() < 1 ))
        {
            return new EntityDoesNotExistException('Request Resulted In Zero Results', ErrorCodes::NO_RESULTS_FOUND);
        }
        return true;
    }
}
