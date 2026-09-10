<?php

namespace RBMowatt\BaseTests;

use Example\Controllers\Api\ExampleApiController;
use Example\ExampleServiceProvider;
use Example\Models\ExampleModel;
use Example\Models\Interfaces\ExampleModelInterface;
use Example\Models\WidgetType;
use Example\Services\ExampleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Rest\ApiResponse;
use RBMowatt\Base\Rest\Query\QueryParser;

/**
 * The example folder is documentation, so it gets exercised like documentation:
 * every path shown in it runs here.
 */
class ExampleTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [ExampleServiceProvider::class]);
    }

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

        Schema::create('widget_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('example', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('widget_type_id');
            $table->string('account_type');
            $table->timestamps();
        });

        WidgetType::create(['name' => 'sprocket']);
        WidgetType::create(['name' => 'flange']);

        ExampleModel::create(['name' => 'alpha', 'widget_type_id' => 1, 'account_type' => 'b']);
        ExampleModel::create(['name' => 'beta', 'widget_type_id' => 2, 'account_type' => 'a']);
        ExampleModel::create(['name' => 'gamma', 'widget_type_id' => 1, 'account_type' => 'c']);
    }

    private function controller(array $query, string $method = 'GET'): ExampleApiController
    {
        $request = Request::create('/api/example', $method, $query);
        $this->app->instance('request', $request);

        return new ExampleApiController(
            new ApiResponse(),
            new QueryParser($request),
            new ExampleService($this->app->make(ExampleModelInterface::class))
        );
    }

    public function testTheProviderBindsTheInterface(): void
    {
        $this->assertInstanceOf(ExampleModel::class, $this->app->make(ExampleModelInterface::class));
    }

    public function testIndexReturnsTheEnvelopeWithPaginationMeta(): void
    {
        $payload = $this->controller([])->index()->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertCount(3, $payload['data']);
        $this->assertSame(3, $payload['meta']['total']);
        $this->assertSame(20, $payload['meta']['per_page']);
    }

    public function testFilterScopeMapping(): void
    {
        $payload = $this->controller(['where' => '[widget.type.id=2]'])->index()->getData(true);

        $this->assertCount(1, $payload['data']);
        $this->assertSame('beta', $payload['data'][0]['name']);
    }

    public function testSortScopeMapping(): void
    {
        $payload = $this->controller(['sort' => 'account_type_ASC'])->index()->getData(true);

        $this->assertSame(['beta', 'alpha', 'gamma'], array_column($payload['data'], 'name'));
    }

    public function testEagerLoadingARelation(): void
    {
        $payload = $this->controller(['with' => '[widgetType]'])->index()->getData(true);

        $this->assertSame('sprocket', $payload['data'][0]['widget_type']['name']);
    }

    public function testCountOnly(): void
    {
        $payload = $this->controller(['count' => 'true'])->index()->getData(true);

        $this->assertSame(3, $payload['data']);
    }

    public function testShowStoreUpdateDestroy(): void
    {
        $created = $this->controller([], 'POST')
            ->store(Request::create('/api/example', 'POST', [
                'name' => 'delta',
                'widget_type_id' => 1,
                'account_type' => 'd',
            ]))
            ->getData(true);

        $id = $created['data']['id'];
        $this->assertSame('delta', $created['data']['name']);

        $shown = $this->controller([])->show($id)->getData(true);
        $this->assertSame('delta', $shown['data']['name']);

        $updated = $this->controller([])
            ->update($id, Request::create('/api/example', 'PUT', ['name' => 'delta prime']))
            ->getData(true);
        $this->assertSame('delta prime', $updated['data']['name']);

        $this->controller([])->destroy($id);
        $this->assertNull(ExampleModel::find($id));
    }

    public function testAnUnknownRelationComesBackAsAnErrorEnvelope(): void
    {
        $payload = $this->controller(['with' => '[nope]'])->index()->getData(true);

        $this->assertFalse($payload['success']);
        $this->assertStringContainsString('Relation Does Not Exist', $payload['error']);
    }
}
