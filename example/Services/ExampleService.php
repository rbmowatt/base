<?php

namespace Example\Services;

use App;
use Exception;
use Example\Models\Interfaces\ExampleModelInterface;
use MowattMedia\Base\ErrorCodes;
use MowattMedia\Base\Services\BaseService;


class ContractService extends BaseService
{
    /**
     * First we set up an array of filter/scope mappings
     * when any of these parameters are encountered by this service
     * the matching Scope will be added rather than adding a peoperty filter
     * right on to the query
     * 
     * can accept either '_' or '.' delimiter
     * add the 'scope'keyword to the method but not the mapping!!!
     */
    protected $scopes = [
        'widget.type.id'=>'byWidgetType',
        //turns into $widget->byWidgetType($queryParam['widget_type_id])
    ];

    /**
     * Next we set up an array of sort/scope mappings
     * when any of these parameters are encountered by this service
     * the sort will be added as a scope rather than applied
     * right on to the query
     * 
     * can accept either '_' or '.' delimiter
     * add the 'scope'keyword to the method but not the mapping!!!
     */

    protected $sortScopes = [
        'account_type'=>'SortByAccountType',
         //turns into $widget->SortByAccountType($queryParam[account_type])
    ];

    /**
     * An array of associations that we don't need the count for
     */
    public $doNotDoCountQueryOn = ['someThingIDontwWantToWasteResourcesCounting'];

    public function __construct(ExampleModelInterface $example)
    {
        //all queries will be run against this model unless instructed otherwise
        $this->primaryModel = $example;

    }
   
    // example override of parent method
    public function update($entity, $args, $callback = false)
    {
        if(in_array('some_key', array_keys($args)))
        {
           //do somethimg with args
        }
        return parent::update($entity, $args, $callback);
    }

    /**
     * Put additional logic revloving around getting data for and proccessing Example entities here
     */
}
