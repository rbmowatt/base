<?php

namespace Example\Services;

use Example\Models\Interfaces\ExampleModelInterface;
use RBMowatt\Base\Services\BaseService;

class ExampleService extends BaseService
{
    /**
     * Columns a caller may filter on with `?column=value`. Nothing is filterable
     * until it is listed here — a column existing on the table is not enough.
     */
    protected $filterable = ['name', 'widget_type_id', 'account_type'];

    /**
     * Columns a caller may pass to `?sort=`. Same rule.
     */
    protected $sortable = ['name', 'widget_type_id'];

    /**
     * First we set up an array of filter/scope mappings
     * when any of these parameters are encountered by this service
     * the matching Scope will be added rather than adding a property filter
     * right on to the query
     *
     * can accept either '_' or '.' delimiter
     * add the 'scope' keyword to the method but not the mapping!!!
     */
    protected $scopes = [
        'widget.type.id' => 'byWidgetType',
        //turns into $example->byWidgetType($queryParam['widget_type_id'])
    ];

    /**
     * Next we set up an array of sort/scope mappings
     * when any of these parameters are encountered by this service
     * the sort will be added as a scope rather than applied
     * right on to the query
     *
     * can accept either '_' or '.' delimiter
     * add the 'scope' keyword to the method but not the mapping!!!
     */
    protected $sortScopes = [
        'account_type' => 'sortByAccountType',
        //turns into $example->sortByAccountType('account_type', 'ASC')
    ];

    /**
     * An array of associations that we don't need the count for
     */
    public $doNotDoCountQueryOn = ['someThingIDontWantToWasteResourcesCounting'];

    public function __construct(ExampleModelInterface $example)
    {
        //all queries will be run against this model unless instructed otherwise
        $this->primaryModel = $example;
    }

    // example override of parent method
    public function update($entity, $args, $callback = '')
    {
        if (in_array('some_key', array_keys($args))) {
            //do something with args
        }

        return parent::update($entity, $args, $callback);
    }

    /**
     * Put additional logic revolving around getting data for and processing
     * Example entities here
     */
}
