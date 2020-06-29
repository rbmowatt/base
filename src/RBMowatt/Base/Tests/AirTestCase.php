<?php

namespace RBMowatt\Base\Tests;

/**
* Base Test Case for air components
*/
ini_set("display_errors", 1);
ini_set("memory_limit", "-1");
ini_set('error_reporting', E_ALL);

use App;
use Exception;
use Mockery as m;
use Tests\TestCase;
use \RBMowatt\Base\Tests\Traits\Mockery;
use \RBMowatt\Base\Tests\Traits\Reflection;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class TestCase extends TestCase
{
    const EXCEPTION_MESSAGE = 'Test Exception';

    use Mockery;
    use Reflection;
    use WithoutMiddleware;

    public function setUp()
    {
        parent::setUp();
        $this->exception = new Exception(self::EXCEPTION_MESSAGE);
        //  echo get_class($this) . "::" . $this->getName() . PHP_EOL;
    }

    /**
    * extends @parent::tearDown
    * @return void
    */
    public function tearDown()
    {
        parent::tearDown();
        m::close();
    }
    /**
    * Compares our default exception with response
    * @param Response $response
    * @return boolean
    */
    protected function validException($response)
    {
        $c = $this->decode($response);
        return stristr($c->error, self::EXCEPTION_MESSAGE) ? true : false;
    }
    /**
    * Look at the response and decide how it should be parsed
    * @param  Response $response
    * @return mixed
    */
    protected function decode($response)
    {
        if (method_exists($response, 'getOriginalContent'))
        {
            return json_decode($response->getOriginalContent());
        }
        if (method_exists($response, 'getData'))
        {
            return $response->getData();
        }
    }

    public function createApplication()
  {
      putenv('DB_DEFAULT=sqlite_testing');
      return parent::createApplication();
  }

  protected function getFactoryClass($class)
  {
      return get_class(App::make($class)) ;
  }


    /**
    * Creates the application.
    *
    * @return Symfony\Component\HttpKernel\HttpKernelInterface
    */
    public function createApplication2()
    {
        $unitTesting = true;

        $testEnvironment = 'testing';

        return require __DIR__ . '/../../../../../../../bootstrap/start.php';
    }
    /**
    * Assert that the given fields were the ones missing
    * @param Response $response
    * @param  array  $fields
    * @return boolean
    */
    public function assertPostFieldsMissing($response, $fields = [])
    {
        $c = $this->decode($response);
        if (!isset($c->validationErrors))
        {
            $this->assertTrue(false);
            return;
        }
        foreach ($fields as $f)
        {
            if (!isset($c->validationErrors->$f))
            {
                $this->assertTrue(false);
                return;
            }
        }
        $this->assertTrue(true);
    }
}
