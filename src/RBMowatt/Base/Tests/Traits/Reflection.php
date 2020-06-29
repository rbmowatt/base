<?php

namespace RBMowatt\Base\Tests\Traits;

use ReflectionClas;

trait Reflection
{
    /**
    * Exposes Private Methods For Testing
    * @param  obj $class
    * @param  string $name  [method name]
    * @return function
    */
    protected static function makeMethodPublic($class, $name)
    {
        $class = new ReflectionClass($class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    /**
    * get the attributes from an eloquent calss
    * @param obj $obj
    * @return array
    */
    public function getAttributes($obj)
    {
        $class = new ReflectionClass($obj);
        $property = $class->getProperty("attributes");
        $property->setAccessible(true);
        return $property->getValue($obj);
    }
}
