<?php

namespace RBMowatt\Base\Tests\Traits;

use Mockery as m;
use Illuminate\Pagination\LengthAwarePaginator;
use RBMowatt\Base\Services\ServiceResultsCollection;

trait Mockery
{
    /**
    * set the mock instace for a test
    * @param string $class
    * @param string $propName
    */
    public function setMock( $class, $register = false, $propName= null)
    {
        $propName = ($propName) ? $propName : $this->getPropName($class);
        $this->{$propName} = m::mock($class);
        if($register)
        {
            $this->instance($class, $this->{$propName});
        }
    }
    /**
    * Mocks the service collection
    * @param  [type]  $data  [description]
    * @param  integer $count [description]
    * @param  integer $limit [description]
    * @param  integer $page  [description]
    * @return [type]         [description]
    */
    public function mockServiceCollection($data, $count = 1, $limit=20, $page=1)
    {
        if($data instanceof LengthAwarePaginator)
        {
            return new ServiceResultsCollection($data, $data);
        }
        $data = is_array($data) ? $data : [$data];
        return new ServiceResultsCollection($data, new LengthAwarePaginator($data, $count, $limit, $page));
    }
    /**
    * Set The Attribute Handlers On A Mock
    * @param  Mockery $mock
    * @param  string $property
    * @param  mixed $value
    * @return void
    */
    public function mattr($mock, $property, $value)
    {
        $mock ->shouldReceive('setAttribute')
        ->with($property, $value)
        ->andReturn($value);
        $mock->shouldReceive('getAttribute')
        ->with($property)
        ->andReturn($value);
    }

    /**
    * parse the prop name from the class name
    * @param  string $str
    * @return string
    */
    protected function getPropName($str, $register = false)
    {
        $ps = explode('\\', $str);
        return strtolower($ps[count($ps) - 1]);
    }
}
