<?php

namespace RBMowatt\Base\Services;

use App;
use DB;
use Elasticsearch\ClientBuilder;
use Exception;
use Illuminate\Routing\Controller as BaseController;
use RBMowatt\Base\ErrorCodes;
use RBMowatt\Base\Exceptions\EntityDoesNotExistException;
use RBMowatt\Base\Services\ServiceResultsCollection;
use RBMowatt\Base\Services\Exceptions\InvalidArgumentsException;
use RBMowatt\Base\Services\Exceptions\InvalidQueryParamException;
use RBMowatt\Base\Services\Exceptions\InvalidRelationException;
use RBMowatt\Base\Services\Exceptions\InvalidWhereFormatException;
use RBMowatt\Base\Services\Exceptions\SortException;
use RBMowatt\Base\Services\Interfaces\ServiceInterface;



abstract class BaseService implements ServiceInterface
{
    /*
    * The model this service will wrap its functionality around unless told otherwise
    */
    protected $primaryModel;
    /*
    A list of key/value pairs that tells the service how to resolve 
    filter params that don't live on the DB schema
    */
    protected $scopes = [];
    /*
    By default we will attach the total count of any relations
    This is additional overhead if you don't actually need that meta info
    place any relations you don't need the count for here
    */
    protected $doNotDoCountQueryOn = [];
    /*
    The default # of results for each GET request
    */
    const DEFAULT_LIMIT = 20;
    const DEFAULT_PAGE = 1;
    /**
     * Valid Sort Order Request Keys
     */
    const SORT_ORDERS = ['ASC', 'DESC', 'asc', 'desc'];
    /**
     * Override the default primary model
     * @param BaseModel $model 
     */
    public function setModel($model)
    {
        $this->primaryModel = $model;
        return $this;
    }
    /**
     * Get default primary model
     * @return BaseModel 
     */
    public function getModel($model)
    {
        return $this->primaryModel;
    }
    /**
     * get the scope key/valeue defined in the local object
     * @return array 
     */
    public function getScopes()
    {
        return $this->scopes;
    }
    /**
     * Find A Single Instance based on PK ( assumes `id` )
     * @param int $id 
     * @return BaseModel
     */
    public function find($id, array $with = [], $selects = [])
    {
        $model = $this->eagerLoad($this->primaryModel, $with);
        $model = $this->select($model, $selects);
        //there's really no extra metadata to relay here so let's just send back the Object
        //not abstract as could be I know but this is already being coupled with Laravel so
        //the chances of the model changing much are slim in our case
        return $model->find($id);
    }
    /**
     * Find with filters other than uid
     * @param  array $wheres A list of filters generally synonymous with SQL "where"
     * @param array $with A list of relations that will map to QueryBuilders "with" method
     * @param array $sorts A list of Sort Orders That will be applies in the order recieved
     * @param int $limit How many records to limit the result to
     * @param int $page What page are we on in a pagination context ?
     * @return ServiceResultsCollection  A beefed up vesrion of Laravels Default Collection
     */
    public function where($wheres, array $with = [], $sorts = [], $selects = [], $limit = self::DEFAULT_LIMIT, $page = self::DEFAULT_PAGE)
    {
        // alias for find
        if (is_numeric($wheres)) return $this->find($wheres, $with, $selects);

        $model = $this->select($this->primaryModel, $selects);
        $model = $this->setWheres($model, $wheres);
        $model = $this->setSorts($model, $sorts);
        $model = $this->eagerLoad($model, $with);
        $result = $model->paginate($limit)->withPath(preg_replace('/&page=\d*/', '', $_SERVER['REQUEST_URI']));
        //we're going to wrap this is a ServiceResultsCollection to add some functionality to make things easier for the consumer to parse
        return new ServiceResultsCollection($this->primaryModel, $result);
    }
    /** 
    * Create an instance based on provided params
    * @param array $params an array of key values to be applied to the entity
    * @param mixed $callback provide a function to be caled AFTER entity saves
    * @return BaseModel
    */
    public function create($params, $callback = '')
    {
        if (!is_array($params) || (array_intersect(array_keys($params), $this->getColumns()) != array_keys($params))) {
            //let's stop bad data as soon as possible
            throw new InvalidArgumentsException('Invalid Arguments');
        }
        $model = App::make(get_class($this->primaryModel));
        foreach ($params as $prop => $value) {
            $model->{$prop} = $value;
        }
        $model->save();
        if (!empty($callback)) {
             //apply any additional logic provided after we save
            $callback($model);
        }
        return $model;
    }
    /** 
    * Update an instance based on provided params
    * @param mixed $entity either an instance of BaseModel or an id
    * @param array $args an array of key values to be applied to the entity
    * @param mixed $callback provide a function to be caled BEFORE entity saves 
    * @return BaseModel
    */
    public function update($entity, $args, $callback = '')
    {
        if (!is_object($entity)) {
            //we got the id rather than the object so let's load it up ourselves
            $entity = $this->primaryModel->find($entity);
        }
        if (array_intersect(array_keys($args), $this->getColumns()) != array_keys($args)) {
            //there shouldn't be any parameters in the request that don't match up with the record
            throw new InvalidArgumentsException('Invalid Arguments');
        }
        foreach ($args as $key => $value) {
            $entity->{$key} = $value;
        }
        if (!empty($callback)) {
            //apply any additional logic provided before we save
            $callback($entity);
        }
        $entity->save();  
        return $entity;
    }
    /**
     * Delete A Single Instance
     * @param int $id record id
     * @return boolean whether delete was successful
     */
    public function remove($id)
    {
        return $this->primaryModel->destroy($id);
    }
    /**
     * Set the filters on query
     * @param BaseModel $model  
     * @param array $wheres 
     * @return BaseModel
     */
    protected function setWheres($model, $wheres)
    {
        foreach ($wheres as $filter) {
            if (count($filter) !== 3){
                //where should always be in format [key, '=', value]
                //if not then Houston we have a problem
                throw new InvalidWhereFormatException('Invalid Format For Where Clauses' . $filter);
            }
            if ($scope = $this->isScope($filter[0])) {
                //this means it's a scope rather than a model property, therefore it must be applied as a function
                $model = $model->{$this->getScopes()[$scope]}($filter[2], $filter[1]);
            } else {
                // there are params that match up directly with the model
                if (is_array($filter[2])) {
                    //if it's an array then it has to be an IN clause
                    $model = $model->whereIn($this->primaryModel->getTable() . '.' . $filter[0], $filter[2]);
                } else {
                    $model = $model->where($this->primaryModel->getTable() . '.' . $filter[0], $filter[1], $filter[2]);
                }
            }
        }
        return $model;
    }
    /**
     * Set the sort options on the query
     * @param BaseModel $model
     * @param array $sorts
     * @return BaseModel
     */
    public function setSorts($model, $sorts)
    {
        foreach ($sorts as $sort) {
            //will throw exception if sort not valid
            $this->checkValidSort($sort);

            if (!in_array($sort[0], $this->getColumns()) && $scope = $this->getSortScope($sort[0])) {
                //in this case the sort isn't based on a model property
                //instead it needs to be passed to a scope dedicated to sort
                $model = $model->{$scope}($sort[0], $sort[1]);
                continue;
            }
            //sort request is a model property all is well and easy, just attach it
            //make surre to add the table namepspacing to avoid conflicts with additional scopes
            $model = $model->orderBy($this->primaryModel->getTable() . '.' . $sort[0], $sort[1]);
        }
        return $model;
    }

    /**
     * Attach Any Eager Loading relations
     * @param Object $model an instance of the model upon which to attach the withs
     * @param array $withs an array of relations
     * @return Object
     */
    protected function eagerLoad($model, $withs)
    {
        $loadedRelations = [];
        //first we'll validate they are good relations
        //exception will be thrown from validateRelations method on error
        foreach ($this->validateRelations($model, $withs) as $with) {
            $model = $model->with($with);
            //here we're going to build a single string for any nested relations rather than sending in a bunch of dupes
            // so instead of sending in with(['first', 'first.second']) you'll only have with(['first.second']) 
            $withKey = $this->getRelationRoot((!is_array($with)) ? $with : array_keys($with)[0]);
            //sometimes theres no point in adding the overhead of a count
            if (!in_array($withKey, $this->doNotDoCountQueryOn) && !in_array($withKey, $loadedRelations)) {
                $model = $model->withCount($withKey);
            }
            //we don't want to duplicate keys so let's tel future loops it's been done
            $loadedRelations[] = $withKey;
        }
        return $model;
    }
    /**
     * Confirms Entity Exists and throws exception if not
     * @return null
     * @throws EntityDoesNotExistException
     */
    public function confirmExistence($ids)
    {
        foreach ((array)$ids as $id) {
            if (!$this->primaryModel->find($id))
                throw new EntityDoesNotExistException('Contract id ' . $id . ' can not be found.', ErrorCodes::NO_RESULTS_FOUND);
        }
    }
    /**
     * This method determines wheter a sort scope is valid
     * and will return the mapped method name if found
     * @param  string $key 
     * @return string
     * @throws SortException
     */
    protected function getSortScope($key)
    {
        foreach ($this->sortScopes as $ssKey => $scopeMethod) {
            //if keys match or sortScope is global and relation names match
            if (trim($ssKey) == trim($key))  return $scopeMethod;
        }
        throw new SortException('Invalid Sort Scope ' . $key);
    }
    /*
    Helper method to make sure that sort order was passed in the coorect format
    */
    protected function checkValidSort($sort)
    {
        if (!is_array($sort) || count($sort) !== 2) {
            throw new SortException('Sort expects 2 paramaters [key,order]');
        }
        if (!in_array($sort[1], self::SORT_ORDERS)) {
            throw new SortException('Invalid Sort Order ' . $sort[1]);
        }
    }

    /**
     * Add Select Clauses To Query
     * @param  BaseModel $model   
     * @param  string $columns 
     * @return BaseModel         
     */
    protected function select($model, $columns)
    {
        $selects = [];
        foreach ($columns as $property) {
            $selects[] = (stristr($property, '.')) ? $c : implode('.', [$model->getTable(), $property]);
        }
        if (count($selects)) {
            //selects were added to append them to query and move on
            return $model->select($selects);
        }
        //here we will namespace the table to avoid collisons or getting data from other joined tables
        return (method_exists($model, 'getTable')) ?
            $model->select(implode('.', [$model->getTable(), '*'])) : $model;
    }

    /**
     * get $this->primaryModel's columns
     * @return [array] [an array of $this->primaryModel's columns]
     */
    public function getColumns()
    {
        return $this->primaryModel->columns();
    }
    /*
    Get the rootmost level of dot syntax relation tree
    */
    protected function getRelationRoot($relation)
    {
        $d = explode('.', $relation);
        return array_shift($d);
    }

    /**
     * Validate the relations on the model that are being asked for
     * @param  BaseModel $model 
     * @param  array $withs 
     * @return array        
     */
    protected function validateRelations($model, $withs)
    {
        //$model = $this->primaryModel;
        //get primary relationship keys
        //get rid of any empty indexes
        $withs = array_filter($withs);
        if (!count($withs)) return [];
        $className = get_class($model);
        $methodNamesFn = function ($m) use ($className) {
            return ($m->class == $className) ? $m->name : null;
        };
        $methodNames = array_filter(array_map($methodNamesFn, $model->getMethods()));
        $diff = array_diff(array_values($withs), array_values($methodNames));
        if ($diff) {
            //we havent found a defined relationship for every relationship requested
            throw new InvalidRelationException($model, $diff);
        }
        return $withs;
    }

    /**
     * Chexks to see if a parameter in the request is a model property or scope
     * @param  string  $key 
     * @return boolean
     * @throws InvalidQueryParamException
     */
    protected function isScope($key)
    {
        if (in_array($key, $this->getColumns())) {
            return false;
        }
        $scopes = array_keys($this->getScopes());
        if (in_array($key, $scopes)) {
            return $key;
        }
        $k = str_replace('_', '.', $key);
        if (in_array($k, $scopes)) {
            return $k;
        }
        throw new InvalidQueryParamException($this, $this->primaryModel, [$key]);
    }
}
