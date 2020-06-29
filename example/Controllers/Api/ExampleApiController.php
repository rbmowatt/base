<?php
namespace Example\Controllers\Api;

use Exception;
use Illuminate\Http\Request;
use Mowattmedia\Base\Controllers\Api\AirBaseApiController as ApiController;
use Mowattmedia\Base\Rest\ApiResponse;
use Query\QueryParser;
use Example\Services\ExampleService as BaseService;

class ExampleApiController extends ApiController
{

    protected $response;
    protected $queryParser;
    protected $baseService;

    public function __construct(ApiResponse $response, QueryParser $queryParser, BaseService $baseService )
    {
        parent::__construct($response);
        $this->queryParser = $queryParser;
        $this->baseService = $baseService;

    }
    /**
    * [index description]
    * @return ApiResponse
    * @throws Exception
    */
    public function index()
    {
        try
        {
            if($this->queryParser->isCount())
            {
                return $this->response->ok($this->baseService->getCountWhere($this->queryParser->getWheres()));
            }
            $result = $this->baseService->where($this->queryParser->getWheres(), $this->queryParser->getWith(),
            $this->queryParser->getSorts(), $this->queryParser->getSelects(), $this->queryParser->getLimit(),$this->queryParser->getPage());
            return $this->response->setMeta($result->getMeta())->ok($result->items());
        }
        catch( Exception $e )
        {
            return $this->response->exception($e);
        }
    }

    /**
    * Show All entities
    * @param  integer $id 
    * @return ApiResponse
    */
    public function show($id)
    {
        try
        {
            $result = $this->baseService->find($id, $this->queryParser->getWith(), $this->queryParser->getSelects());
            return $this->response->ok($result);
        }
        catch( Exception $e )
        {
            return $this->response->exception($e);
        }
    }
    /**
    * Create A New entity
    * @param  Request $request
    * @return ApiResponse
    */
    public function store(Request $request)
    {
        try
        {
            $result = $this->baseService->create($request->all());
            return $this->response->ok($result);
        }
        catch( Exception $e )
        {
            return $this->response->exception($e);
        }
    }
    /**
    * [update description]
    * @param  integer $id [entities table id]
    * @param  Request $request
    * @return ApiResponse
    */
    public function update($id, Request $request)
    {
        try
        {
            $result = $this->baseService->update($this->baseService->find($id), $request->except('id'));
            return $this->response->ok($result);
        }
        catch( Exception $e )
        {
            return $this->response->exception($e);
        }
    }
    /**
    * Delete A entity
    * @param  integer $id [entities table id]
    * @return ApiResponse
    */
    public function destroy($id)
    {
        try
        {
            return $this->response->ok($this->baseService->remove($id));
        }
        catch( Exception $e )
        {
            return $this->response->exception($e);
        }
    }

}
