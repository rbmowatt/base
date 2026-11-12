<?php

namespace RBMowatt\BaseTests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Models\BaseModel;
use RBMowatt\Base\Services\BaseService;

class Shelf extends BaseModel
{
    protected $table = 'shelves';
    public $timestamps = false;
}

class ShelfService extends BaseService
{
    protected $sortable = ['name'];

    public function __construct()
    {
        $this->primaryModel = new Shelf();
    }
}

class WherePageTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('shelves', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
            Shelf::query()->insert(['name' => $name]);
        }
    }

    private function names($result): array
    {
        return $result->items()->pluck('name')->all();
    }

    public function testReturnsThePageItIsAskedFor(): void
    {
        $result = (new ShelfService())->where([], [], [['name', 'asc']], [], 2, 2);

        $this->assertSame(['c', 'd'], $this->names($result));
        $this->assertSame(2, $result->getMeta('current_page'));
    }

    public function testThePageArgumentWinsOverThePageInTheRequest(): void
    {
        $this->app->instance('request', Request::create('/shelves', 'GET', ['page' => 3]));

        $result = (new ShelfService())->where([], [], [['name', 'asc']], [], 2, 1);

        $this->assertSame(['a', 'b'], $this->names($result));
        $this->assertSame(1, $result->getMeta('current_page'));
    }
}
