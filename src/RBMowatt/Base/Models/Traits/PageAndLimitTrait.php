<?php namespace RBMowatt\Base\Models\Traits;

use DB;

trait PageAndLimitTrait
{
    /**
    * query scope nPerGroup
    *
    * @return void
    */
    public function scopeLimitTo($query, $group, $n = 10)
    {
        // queried table
        $table = ($this->getTable());

        // initialize MySQL variables inline
        $query->from( DB::raw("(SELECT @rank:=0, @group:=0) as vars, {$table}") );

        // if no columns already selected, let's select *
        if ( ! $query->getQuery()->columns)
        {
            $query->select("{$table}.*");
        }

        // make sure column aliases are unique
        $groupAlias = 'group_'.md5(time());
        $rankAlias  = 'rank_'.md5(time());

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
    * @return void
    */
    public function scopePage($query, $group, $offset=1, $n = 10)
    {
        $table = ($this->getTable());
        $query->from( DB::raw("(SELECT @rank:=0, @group:=0 ) as vars, {$table}") );


        // if no columns already selected, let's select *
        if ( ! $query->getQuery()->columns)
        {
            $query->select("{$table}.*");
        }

        // make sure column aliases are unique
        $groupAlias = 'group_'.md5(time());
        $rankAlias  = 'rank_'.md5(time());

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
        * @return void
        */
        public function scopePt($query, $group, $offset=1, $n = 10)
        {
           $nq = $this->newQuery();
         // dd($query->getQuery()->joins[0]);
            //$group = $this->getFk($group);
            //$
            // queried table
            $table = ($this->getTable());
            // make sure column aliases are unique
            //$groupAlias = 'group_'.md5(time());
            //$rankAlias  = 'rank_'.md5(time());
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
            //$nq->mergeBindings($query->getQuery());
            //dd($nq->toSql());

            $query->join(DB::raw("({$nq->toSql()}) as t3"), function($join) use ($table){
                $join->on($table. ".id", '=', "t3.id");
            });
            $query->where($rankAlias, '>', ($offset - 1) * $n)
            ->where($rankAlias, '<',  ($n  * $offset) + 1);

            return $query;
            //return $nq;
    dd($query->toSql());
            // make sure first order clause is the group order
            $query->getQuery()->orders = (array) $query->getQuery()->orders;
            array_unshift($query->getQuery()->orders, ['column' => $group, 'direction' => 'asc']);
            //$query->where($rankAlias, '>', ($offset - 1) * $n)
            //->where($rankAlias, '<',  ($n  * $offset) + 1);

            // prepare subquery
            $subQuery = $query->toSql();
            //dd($subQuery );

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
}
