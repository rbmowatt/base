<?php namespace RBMowatt\Base\Services;

/**
 * A Service Results Collection Takes a Collection of Results and provides both them and the medtadata in a standard format
 * 
 */

class ServiceResultsCollection
{
    protected $items;

    public function __construct($model, $results)
    {
        $this->results = $results;
        //first we have to figure out what method we need to call to get our base collection
        //Query Bulider will return plain objects on raw queries
        if(method_exists($results, 'getCollection'))
        {
            //it's a collection
            $this->items = $results->getCollection();
        }
        elseif(method_exists($results, 'all'))
        {
            //its a model
            $this->items = collect($results->all());
        }
        else {
            //it's just raw data
            $this->items = $results;
        }
    }
    /**
    * Provides all the metainfo about the result particularly pagination
    * @return [type] [description]
    */
    public function getMeta($property = null)
    {
        if (method_exists($this->results, 'total')) {
            $meta = [
                'total'         => $this->results->total(),
                'per_page'      => $this->results->perPage(),
                'current_page'  => $this->results->currentPage(),
                'last_page'     => $this->results->lastPage(),
                'next_page_url' => $this->results->nextPageUrl(),
                'prev_page_url' => $this->results->previousPageUrl(),
                'from'          => $this->results->firstItem(),
                'to'            => $this->results->lastItem()
            ];
            return $property ? $meta[$property] : $meta;
        } else {
            return [];
        }
    }
    /**
    * [items description]
    * @return [type] [description]
    */
    public function items()
    {
        return $this->items;
    }
    /**
     * gat a count of items
     */
    public function count()
    {
        return count($this->items);
    }
    /**
     * transform items as needed
     * @param function $fn
     */
    public function transform($fn)
    {
        foreach($this->items as &$item)
        {
            $fn($item);
        }
    }
    /**
    * Set the Items manually
    * @param array $items 
    * @return ServiceResultsCollection
    */
    public function setItems($items)
    {
        $this->items = $items;
        return $this;
    }
}
