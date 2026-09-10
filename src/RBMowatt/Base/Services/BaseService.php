<?php

namespace RBMowatt\Base\Services;

use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\App;
use RBMowatt\Base\ErrorCodes;
use RBMowatt\Base\Services\Exceptions\AmbiguousQueryParamException;
use RBMowatt\Base\Exceptions\EntityDoesNotExistException;
use RBMowatt\Base\Models\BaseModel;
use RBMowatt\Base\Services\ServiceResultsCollection;
use RBMowatt\Base\Services\Exceptions\InvalidArgumentsException;
use RBMowatt\Base\Services\Exceptions\InvalidQueryParamException;
use RBMowatt\Base\Services\Exceptions\InvalidRelationException;
use RBMowatt\Base\Services\Exceptions\InvalidWhereFormatException;
use RBMowatt\Base\Services\Exceptions\SortException;
use RBMowatt\Base\Services\Exceptions\UnboundedResultException;
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
    Same idea as $scopes but for sorts. Declared here because getSortScope() reads it
    on any sort that isn't a real column, and a Service that didn't define it got
    "Undefined property" instead of the SortException it should have raised.
    */
    protected $sortScopes = [];
    /*
    Columns this service will accept as `?column=value` filters. Empty means none.

    This used to be "any column on the table", which made every column a query
    oracle: the parser turns `?password_hash=>$2y$10$K` into a real where clause,
    `?count=true` answers it in one cheap integer, and $hidden does nothing because
    it only governs serialization. A hash or a reset token falls to a character-at-
    a-time binary search from that. List what callers are allowed to filter on.
    */
    protected $filterable = [];
    /*
    Columns this service will accept in `?sort=`. Empty means none, and the same
    reasoning applies: ordering by a column the caller cannot otherwise see still
    leaks where a row sits relative to the others.
    */
    protected $sortable = [];
    /*
    By default we will attach the total count of any relations
    This is additional overhead if you don't actually need that meta info
    place any relations you don't need the count for here
    */
    protected $doNotDoCountQueryOn = [];
    /*
    How many hops a `?with=` path may take. Each hop is a query and, unless the
    root is in $doNotDoCountQueryOn, a count query alongside it, so the depth is
    what a caller multiplies work by. Raise it on a service that genuinely needs
    a deeper path.
    */
    protected $maxRelationDepth = 3;
    /*
    The ceiling on all(). Not a page size — the number of rows the service is
    willing to hand back in one unpaginated read before it refuses.
    */
    protected $maxUnpaginated = 500;
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
    public function getModel()
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
     * Columns callers may filter on
     * @return array<int, string>
     */
    public function getFilterable()
    {
        return $this->filterable;
    }
    /**
     * Columns callers may sort by
     * @return array<int, string>
     */
    public function getSortable()
    {
        return $this->sortable;
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
     * @param  array<int, mixed>|int|string $wheres A list of filters generally synonymous with SQL "where", or a primary key, which is routed to find()
     * @param array<int, mixed> $with A list of relations that will map to QueryBuilders "with" method
     * @param array<int, mixed> $sorts A list of Sort Orders That will be applies in the order recieved
     * @param array<int, string> $selects
     * @param int $limit How many records to limit the result to
     * @param int $page What page are we on in a pagination context ?
     * @return ServiceResultsCollection|BaseModel|null A beefed up vesrion of Laravels Default Collection, or the single model when $wheres is a key
     */
    public function where($wheres, array $with = [], $sorts = [], $selects = [], $limit = self::DEFAULT_LIMIT, $page = self::DEFAULT_PAGE)
    {
        // alias for find
        if (is_numeric($wheres)) return $this->find($wheres, $with, $selects);

        $model = $this->select($this->primaryModel, $selects);
        $model = $this->setWheres($model, $wheres);
        $model = $this->setSorts($model, $sorts);
        $model = $this->eagerLoad($model, $with);
        $result = $model->paginate($limit);
        if ($path = $this->paginationPath())
        {
            $result = $result->withPath($path);
        }
        //we're going to wrap this is a ServiceResultsCollection to add some functionality to make things easier for the consumer to parse
        return new ServiceResultsCollection($result);
    }
    /**
     * Every matching row, for the cases pagination gets in the way of — a dropdown,
     * an export, a lookup table.
     *
     * Bounded rather than unbounded. It reads $maxUnpaginated + 1 rows and throws
     * if that many come back, so a caller who quietly outgrows the ceiling finds
     * out instead of shipping a truncated list as if it were complete. The extra
     * row is why this is one query and not a count followed by a select.
     *
     * Filters and sorts go through the same allowlists where() uses.
     *
     * @param array<int, mixed> $wheres
     * @param array<int, mixed> $with
     * @param array<int, mixed> $sorts
     * @param array<int, string> $selects
     * @return ServiceResultsCollection
     * @throws UnboundedResultException
     */
    public function all($wheres = [], array $with = [], $sorts = [], $selects = [])
    {
        $model = $this->select($this->primaryModel, $selects);
        $model = $this->setWheres($model, $wheres);
        $model = $this->setSorts($model, $sorts);
        $model = $this->eagerLoad($model, $with);

        $rows = $model->limit($this->maxUnpaginated + 1)->get();

        if ($rows->count() > $this->maxUnpaginated) {
            throw new UnboundedResultException($this->primaryModel, $this->maxUnpaginated);
        }

        return new ServiceResultsCollection($rows);
    }
    /**
     * The path pagination links are built from, or null when there is no request.
     *
     * Reads the container's request rather than $_SERVER: outside a web request
     * $_SERVER has no REQUEST_URI at all, and preg_replace() on that null both
     * raised a deprecation and handed the paginator a null path.
     *
     * @return string|null
     */
    protected function paginationPath()
    {
        if (!App::bound('request'))
        {
            return null;
        }

        return preg_replace('/&page=\d*/', '', App::make('request')->getRequestUri());
    }
    /**
     * Count the rows matching a set of where clauses.
     *
     * Takes the same $wheres QueryParser::getWheres() produces, and runs COUNT
     * rather than fetching a page, so it stays cheap on large tables. Relations,
     * sorts and selects are all pointless here and are not accepted.
     *
     * @param array $wheres
     * @return int
     */
    public function getCountWhere($wheres = [])
    {
        return $this->setWheres($this->primaryModel->newQuery(), $wheres)->count();
    }
    /**
    * Create an instance based on provided params
    * @param array<string, mixed>|mixed $params an array of key values to be applied to the entity
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
        $this->guardMassAssignment($model, $params);
        $model->fill($params);
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
        $this->guardMassAssignment($entity, $args);
        $entity->fill($args);
        if (!empty($callback)) {
            //apply any additional logic provided before we save
            $callback($entity);
        }
        $entity->save();  
        return $entity;
    }
    /**
     * Reject any key the model's own $fillable/$guarded would not accept.
     *
     * create() and update() used to assign with $model->{$key} = $value, which is
     * setAttribute() and carries no mass-assignment check at all, so $fillable was
     * decorative: any real column was writable straight off the request, is_admin
     * and password included. fill() alone is not enough either, because Eloquent
     * only throws on a totally-guarded model and otherwise drops the offending key
     * in silence, which hands the caller a saved model that quietly ignored half
     * the payload.
     *
     * @param BaseModel $model
     * @param array<string, mixed> $params
     * @return void
     * @throws MassAssignmentException
     */
    protected function guardMassAssignment($model, array $params)
    {
        $blocked = array_values(array_filter(
            array_keys($params),
            function ($key) use ($model) {
                return !$model->isFillable($key);
            }
        ));

        if ($blocked) {
            throw new MassAssignmentException(sprintf(
                'Add [%s] to fillable property to allow mass assignment on [%s].',
                implode(', ', $blocked),
                get_class($model)
            ));
        }
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
     * @param BaseModel|\Illuminate\Database\Eloquent\Builder<BaseModel> $model
     * @param array<int, mixed> $wheres
     * @return BaseModel|\Illuminate\Database\Eloquent\Builder<BaseModel>
     */
    protected function setWheres($model, $wheres)
    {
        foreach ($wheres as $filter) {
            if (count($filter) !== 3){
                //where should always be in format [key, '=', value]
                //if not then Houston we have a problem
                throw new InvalidWhereFormatException('Invalid format for where clause: ' . json_encode($filter));
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
     * @param BaseModel|\Illuminate\Database\Eloquent\Builder<BaseModel> $model
     * @param array<int, mixed> $sorts
     * @return BaseModel|\Illuminate\Database\Eloquent\Builder<BaseModel>
     */
    public function setSorts($model, $sorts)
    {
        foreach ($sorts as $sort) {
            //will throw exception if sort not valid
            $this->checkValidSort($sort);

            if (!$this->isSortableColumn($sort[0]) && $scope = $this->getSortScope($sort[0])) {
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
     * @param BaseModel|\Illuminate\Database\Eloquent\Builder<BaseModel> $model an instance of the model upon which to attach the withs
     * @param array<int, mixed> $withs an array of relations
     * @return BaseModel|\Illuminate\Database\Eloquent\Builder<BaseModel>
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
     * @return void
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
     * Is this sort key a column the service has opened up for sorting?
     *
     * A false here sends the key on to getSortScope(), which throws if it is not
     * a declared sort scope either.
     *
     * @param  string $key
     * @return bool
     */
    protected function isSortableColumn($key)
    {
        return in_array($key, $this->getSortable(), true) && in_array($key, $this->getColumns());
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
     *
     * @param  BaseModel|\Illuminate\Database\Eloquent\Builder<BaseModel> $model
     * @param  array<int, string> $columns
     * @return \Illuminate\Database\Eloquent\Builder<BaseModel>
     */
    protected function select($model, $columns)
    {
        // The table name comes off the service's own model: by the time eagerLoad()
        // has run, $model is a Builder, and a Builder forwards unknown calls to the
        // query builder, which has no getTable().
        $table = $this->primaryModel->getTable();
        $selects = [];
        foreach ($columns as $property) {
            // an already-qualified column passes through; $c here was an undefined
            // variable, so a dotted select produced null and three null-argument
            // deprecations on the way down into the query builder
            $selects[] = (stristr($property, '.')) ? $property : implode('.', [$table, $property]);
        }
        if (count($selects)) {
            //selects were added to append them to query and move on
            return $model->select($selects);
        }
        //here we will namespace the table to avoid collisons or getting data from other joined tables
        return $model->select(implode('.', [$table, '*']));
    }

    /**
     * get $this->primaryModel's columns
     * @return array<int, string>
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
     *
     * Every segment of a dotted path is resolved against the model at that level.
     * This used to check only the root and hand the rest of the path straight to
     * Eloquent's with(), so one authorized root relation walked the whole object
     * graph behind it: ?with=tokens.account.tokens returned the related account
     * and its tokens to a caller authorized for nothing but the primary resource.
     * Each hop is also another query plus a withCount, so an unbounded path is a
     * cheap way to multiply work per request — hence $maxRelationDepth.
     *
     * @param  BaseModel|\Illuminate\Database\Eloquent\Builder<BaseModel> $model
     * @param  array $withs
     * @return array
     * @throws InvalidRelationException
     */
    protected function validateRelations($model, $withs)
    {
        //get rid of any empty indexes
        $withs = array_filter($withs);
        if (!count($withs)) return [];

        $primary = $this->primaryModel;
        $bad = [];

        foreach (array_values($withs) as $with) {
            $path = (!is_array($with)) ? $with : array_keys($with)[0];
            if (!$this->relationPathResolves($primary, $path)) {
                $bad[] = $path;
            }
        }

        if ($bad) {
            //we havent found a defined relationship for every relationship requested
            throw new InvalidRelationException($primary, $bad);
        }
        return $withs;
    }

    /**
     * Walk a dotted relation path, hop by hop, from the primary model.
     *
     * @param  BaseModel $model
     * @param  string $path
     * @return bool
     */
    protected function relationPathResolves($model, $path)
    {
        $segments = explode('.', $path);

        if (count($segments) > $this->maxRelationDepth) {
            return false;
        }

        $current = $model;
        foreach ($segments as $segment) {
            if (!$current instanceof BaseModel) {
                // a relation pointing at a plain Eloquent model ends the walk: there
                // is no relationships() on it to check the next hop against
                return false;
            }
            // read the related class out of the map already in hand rather than
            // calling getRelationshipModel(), which asks for the whole map again
            $relations = $current->relationships();
            if (!array_key_exists($segment, $relations)) {
                return false;
            }
            $current = App::make($relations[$segment]['model']);
        }
        return true;
    }

    /**
     * Checks to see if a parameter in the request is a model property or scope
     *
     * Returns the scope key to call, or false when $key is a real column. Callers
     * rely on the returned key, so this is not a plain boolean.
     *
     * @param  string  $key
     * @return string|false
     * @throws InvalidQueryParamException
     */
    protected function isScope($key)
    {
        // A column has to be listed AND real. Requiring both means a typo in
        // $filterable is an InvalidQueryParamException rather than a QueryException
        // carrying the statement back to the caller.
        $isColumn = in_array($key, $this->getColumns());
        $isAllowlisted = in_array($key, $this->getFilterable(), true);

        if ($isAllowlisted && $isColumn) {
            // an explicit $filterable entry settles it, even when a scope of the
            // same name exists
            return false;
        }

        $scopeKey = $this->matchScope($key);

        if ($scopeKey !== null && $isColumn) {
            // both readings are live and nothing says which was meant
            throw new AmbiguousQueryParamException($key, $scopeKey);
        }
        if ($scopeKey !== null) {
            return $scopeKey;
        }
        throw new InvalidQueryParamException($this, $this->primaryModel, [$key]);
    }

    /**
     * The declared scope key this request key maps to, or null.
     *
     * A scope may be declared with dots (`widget.type.id`) and arrive with
     * underscores, since a query string cannot carry the dotted form cleanly.
     *
     * @param  string $key
     * @return string|null
     */
    protected function matchScope($key)
    {
        $scopes = array_keys($this->getScopes());

        if (in_array($key, $scopes)) {
            return $key;
        }
        $dotted = str_replace('_', '.', $key);
        if (in_array($dotted, $scopes)) {
            return $dotted;
        }
        return null;
    }
}
