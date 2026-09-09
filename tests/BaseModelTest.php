<?php

namespace RBMowatt\BaseTests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Models\BaseModel;

class Widget extends BaseModel
{
    protected $table = 'widgets';
}

class BaseModelTest extends TestCase
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

        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    public function testFilterKeepsOnlyRealColumns(): void
    {
        $filtered = (new Widget())->filter([
            'name' => 'a widget',
            'not_a_column' => 'dropped',
        ]);

        $this->assertSame(['name' => 'a widget'], $filtered);
    }

    public function testFilterOnAnEmptyPayload(): void
    {
        $this->assertSame([], (new Widget())->filter([]));
    }
}
