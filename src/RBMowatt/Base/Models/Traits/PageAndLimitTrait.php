<?php namespace RBMowatt\Base\Models\Traits;

use Illuminate\Support\Facades\DB;
use RBMowatt\Base\Models\Exceptions\InvalidArgumentsException;

trait PageAndLimitTrait
{
    /**
    * Resolve $group to a column on this model, quoted for the grammar.
    *
    * All three scopes below build their MySQL user-variable trick with DB::raw,
    * and $group was interpolated into it as-is. That is reachable from a request:
    * BaseService::setWheres() calls a mapped scope as $model->{$scope}($value, $op),
    * so a service with `$scopes = ['group' => 'limitTo']` put the caller's string
    * straight into the statement — verified producing
    * `@group = 1) UNION SELECT password_hash,1,1 FROM accts -- ` inside the SELECT.
    *
    * A binding cannot stand in for a column reference here, so the value is checked
    * against the real column list and then quoted, which is the same thing the
    * query grammar would do for a column it was given properly.
    *
    * @param  \Illuminate\Database\Eloquent\Builder<static> $query
    * @param  mixed $group
    * @return string
    * @throws InvalidArgumentsException
    */
    protected function resolveGroupColumn($query, $group)
    {
        if (!is_string($group) || !in_array($group, $this->columns(), true))
        {
            throw new InvalidArgumentsException(
                'Grouping column must be a column on ' . $this->getTable()
            );
        }

        return $query->getQuery()->getGrammar()->wrap($this->getTable() . '.' . $group);
    }

    /**
    * The row count per group, as a positive int.
    *
    * setWheres() passes a scope (value, operator), so a service that maps a request
    * key onto one of these scopes hands the operator in as $n. Anything that is not
    * a positive number is not a row count.
    *
    * @param  mixed $n
    * @return int
    * @throws InvalidArgumentsException
    */
    protected function resolveGroupSize($n)
    {
        if (!is_numeric($n) || (int) $n < 1)
        {
            throw new InvalidArgumentsException('Group size must be a positive integer');
        }

        return (int) $n;
    }
    /**
    * query scope nPerGroup
    *
    * @param \Illuminate\Database\Eloquent\Builder<static> $query
    * @return \Illuminate\Database\Eloquent\Builder<static>
    */
    public function scopeLimitTo($query, $group, $n = 10)
    {
        // queried table
        $table = ($this->getTable());
        $group = $this->resolveGroupColumn($query, $group);
        $n = $this->resolveGroupSize($n);

        // initialize MySQL variables inline
        $query->from( DB::raw("(SELECT @rank:=0, @group:=0) as vars, {$table}") );

        // if no columns already selected, let's select *
        if ( ! $query->getQuery()->columns)
        {
            $query->select("{$table}.*");
        }

        // make sure column aliases are unique
        $groupAlias = 'group_'.md5((string) time());
        $rankAlias  = 'rank_'.md5((string) time());

        // apply mysql variables
        $query->addSelect(DB::raw(
            "@rank := IF(@group = {$group}, @rank+1, 1) as {$rankAlias}, @group := {$group} as {$groupAlias}"
        ));

        // make sure first order clause is the group order
        $query->getQuery()->orders = (array) $query->getQuery()->orders;
        array_unshift($query->getQuery()->orders, ['column' => $group, 'direction' => 'asc']);

        // prepare subquery
        $subQuery = $query->toSql();

        // prepare new main base Query\Builder
        $newBase = $this->newQuery()
        ->from(DB::raw("({$subQuery}) as {$table}"))
        ->mergeBindings($query->getQuery())
        ->where($rankAlias, '<=', $n)
        ->getQuery();

        // replace underlying builder to get rid of previous clauses
        return $query->setQuery($newBase);
    }

    /**
    * query scope nPerGroup
    *
    * @param \Illuminate\Database\Eloquent\Builder<static> $query
    * @return \Illuminate\Database\Eloquent\Builder<static>
    */
    public function scopePage($query, $group, $offset=1, $n = 10)
    {
        $table = ($this->getTable());
        $group = $this->resolveGroupColumn($query, $group);
        $n = $this->resolveGroupSize($n);
        $offset = $this->resolveGroupSize($offset);
        $query->from( DB::raw("(SELECT @rank:=0, @group:=0 ) as vars, {$table}") );


        // if no columns already selected, let's select *
        if ( ! $query->getQuery()->columns)
        {
            $query->select("{$table}.*");
        }

        // make sure column aliases are unique
        $groupAlias = 'group_'.md5((string) time());
        $rankAlias  = 'rank_'.md5((string) time());

        // apply mysql variables
        $query->addSelect(DB::raw(
            "@rank := IF(@group = {$group}, @rank+1, 1) as {$rankAlias}, @group := {$group} as {$groupAlias}"
        ));

        // make sure first order clause is the group order
        $query->getQuery()->orders = (array) $query->getQuery()->orders;
        array_unshift($query->getQuery()->orders, ['column' => $group, 'direction' => 'asc']);

        // prepare subquery
        $subQuery = $query->toSql();

        // prepare new main base Query\Builder
        $newBase = $this->newQuery()
        ->from(DB::raw("({$subQuery}) as {$table}"))
        ->mergeBindings($query->getQuery())
        ->where($rankAlias, '>', ($offset - 1) * $n)
        ->where($rankAlias, '<',  ($n  * $offset) + 1)
        ->getQuery();

        // replace underlying builder to get rid of previous clauses
        //return $query->addSelect($subQuery);
        return $query->setQuery($newBase);
    }


        /**
        * query scope nPerGroup
        *
        * @param \Illuminate\Database\Eloquent\Builder<static> $query
        * @return \Illuminate\Database\Eloquent\Builder<static>
        */
        public function scopePt($query, $group, $offset=1, $n = 10)
        {
            $nq = $this->newQuery();
            $table = ($this->getTable());
            $group = $this->resolveGroupColumn($query, $group);
            $n = $this->resolveGroupSize($n);
            $offset = $this->resolveGroupSize($offset);
            // initialize MySQL variables inline
            $nq->from( DB::raw("(SELECT @rank:=0, @group:=0 ) as vars, {$table}") );


            // if no columns already selected, let's select *
            if ( ! $query->getQuery()->columns)
            {
                $nq->select("{$table}.*");
            }

            // make sure column aliases are unique
            $groupAlias = 'group_1';
            $rankAlias  = 'rank_1';

            // apply mysql variables
            $nq->addSelect(DB::raw(
                "@rank := IF(@group = {$group}, @rank+1, 1) as {$rankAlias}, @group := {$group} as {$groupAlias}"
            ));

            $nq->mergeBindings($query->getQuery());

            $query->join(DB::raw("({$nq->toSql()}) as t3"), function($join) use ($table){
                $join->on($table. ".id", '=', "t3.id");
            });
            $query->where($rankAlias, '>', ($offset - 1) * $n)
            ->where($rankAlias, '<',  ($n  * $offset) + 1);

            return $query;
        }
}
