<?php namespace Example\Models;

use MowattMedia\Base\Models\BaseModel;
use Example\Models\Interfaces\ExampleModelInterface;

class Example extends BaseModel implements ExampleModelInterface
{
    //just set the table name and you have a default connector to example table
    protected $table = 'example';
}
