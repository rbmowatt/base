<?php

namespace RBMowatt\Base\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use RBMowatt\Base\ErrorCodes;
use RBMowatt\Base\Exceptions\EntityDoesNotExistException;
use RBMowatt\Base\Rest\ApiResponse;
use RBMowatt\Base\Rest\Traits\RestValidationTrait;
use RBMowatt\Base\Services\ServiceResultsCollection;

class BaseApiController extends Controller
{
    use RestValidationTrait;

    const REQUIRED = 'required';

    protected $aclGuard = null;
    protected $hydratedAclGuard = null;
    protected $response;
    protected $request;
    protected $user;

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
