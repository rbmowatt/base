<?php

namespace RBMowatt\Base\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use RBMowatt\Base\ErrorCodes;
use RBMowatt\Base\Exceptions\EntityDoesNotExistException;
use RBMowatt\Base\Rest\Interfaces\ApiResponseInterface;
use RBMowatt\Base\Rest\Traits\RestValidationTrait;
use RBMowatt\Base\Services\ServiceResultsCollection;

class BaseApiController extends Controller
{
    use RestValidationTrait;

    protected $aclGuard = null;
    protected $hydratedAclGuard = null;
    protected $response;
    protected $request;
    protected $user;

    /**
    * Name of the auth guard used to resolve $user. Null resolves against
    * auth.defaults.guard. Set it in a subclass to pin a specific guard.
    */
    protected $guard = null;

    public function __construct(ApiResponseInterface $response)
    {
        $this->response = $response;
        $this->request = App::make(Request::class);
        $this->user = $this->resolveUser();
    }

    /**
    * Resolves against auth.defaults.guard rather than naming a guard here. Laravel
    * 11 dropped `api` from the stock config/auth.php, so hardcoding it throws
    * "Auth guard [api] is not defined" on a fresh app, from the constructor of every
    * subclass, before any action runs.
    *
    * @return mixed the authenticated user, or null when the app has no auth bound
    */
    protected function resolveUser()
    {
        if (!App::bound('auth'))
        {
            return null;
        }
        return Auth::guard($this->guard)->user();
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
