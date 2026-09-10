<?php

namespace Example\Controllers\Api;

use Exception;
use Illuminate\Http\Request;
use RBMowatt\Base\Controllers\Api\BaseApiController;
use RBMowatt\Base\Rest\ApiResponse;
use RBMowatt\Base\Rest\Query\QueryParser;
use Example\Requests\ExampleStoreRequest;
use Example\Services\ExampleService;

class ExampleApiController extends BaseApiController
{
    protected $queryParser;
    protected $exampleService;

    public function __construct(ApiResponse $response, QueryParser $queryParser, ExampleService $exampleService)
    {
        parent::__construct($response);
        $this->queryParser = $queryParser;
        $this->exampleService = $exampleService;
    }

    /**
    * List entities
    * @return \Illuminate\Http\JsonResponse
    */
    public function index()
    {
        try
        {
            if($this->queryParser->isCount())
            {
                return $this->response->ok($this->exampleService->getCountWhere($this->queryParser->getWheres()));
            }
            $result = $this->exampleService->where(
                $this->queryParser->getWheres(),
                $this->queryParser->getWith(),
                $this->queryParser->getSorts(),
                $this->queryParser->getSelects(),
                $this->queryParser->getLimit(),
                $this->queryParser->getPage()
            );
            return $this->response->setMeta($result->getMeta())->ok($result->items());
        }
        catch( Exception $e )
        {
            return $this->response->exception($e);
        }
    }

    /**
    * Show a single entity
    * @param  integer $id
    * @return \Illuminate\Http\JsonResponse
    */
    public function show($id)
    {
        try
        {
            $result = $this->exampleService->find($id, $this->queryParser->getWith(), $this->queryParser->getSelects());
            return $this->response->ok($result);
        }
        catch( Exception $e )
        {
            return $this->response->exception($e);
        }
    }

    /**
    * Create A New entity
    *
    * Takes ExampleStoreRequest rather than Request: validation and the permission
    * check run during injection, before this body, so neither the Controller nor
    * the Service has to carry them. validated() hands the Service exactly the keys
    * the rules named, which is what the model's $fillable then accepts.
    *
    * @param  ExampleStoreRequest $request
    * @return \Illuminate\Http\JsonResponse
    */
    public function store(ExampleStoreRequest $request)
    {
        try
        {
            $result = $this->exampleService->create($request->validated());
            return $this->response->ok($result);
        }
        catch( Exception $e )
        {
            return $this->response->exception($e);
        }
    }

    /**
    * Update an entity
    * @param  integer $id [entities table id]
    * @param  Request $request
    * @return \Illuminate\Http\JsonResponse
    */
    public function update($id, Request $request)
    {
        try
        {
            $result = $this->exampleService->update($this->exampleService->find($id), $request->except('id'));
            return $this->response->ok($result);
        }
        catch( Exception $e )
        {
            return $this->response->exception($e);
        }
    }

    /**
    * Delete an entity
    * @param  integer $id [entities table id]
    * @return \Illuminate\Http\JsonResponse
    */
    public function destroy($id)
    {
        try
        {
            return $this->response->ok($this->exampleService->remove($id));
        }
        catch( Exception $e )
        {
            return $this->response->exception($e);
        }
    }
}
