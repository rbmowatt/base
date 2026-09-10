<?php

namespace RBMowatt\BaseTests;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Models\BaseModel;
use RBMowatt\Base\Services\BaseService;
use RBMowatt\Base\Services\Exceptions\InvalidRelationException;
use RBMowatt\Base\Services\Exceptions\SortException;

class Gizmo extends BaseModel
{
    protected $table = 'gizmos';

    protected $fillable = ['name', 'type_id'];

    public function parts(): HasMany
    {
        return $this->hasMany(GizmoPart::class, 'gizmo_id');
    }
}

class GizmoPart extends BaseModel
{
    protected $table = 'gizmo_parts';

    protected $fillable = ['gizmo_id', 'label'];
}

class GizmoService extends BaseService
{
    public function __construct(Gizmo $gizmo)
    {
        $this->primaryModel = $gizmo;
    }
}

class BaseServiceTest extends TestCase
{
    public function testGetModelTakesNoArgument(): void
    {
        $service = new GizmoService(new Gizmo());
        $swapped = new GizmoPart();

        $this->assertInstanceOf(Gizmo::class, $service->getModel());
        $this->assertSame($swapped, $service->setModel($swapped)->getModel());
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

        Schema::create('gizmos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('type_id');
            $table->timestamps();
        });

        Schema::create('gizmo_parts', function (Blueprint $table) {
            $table->id();
            $table->integer('gizmo_id');
            $table->string('label');
            $table->timestamps();
        });

        Gizmo::create(['name' => 'alpha', 'type_id' => 1]);
        Gizmo::create(['name' => 'beta', 'type_id' => 2]);
        GizmoPart::create(['gizmo_id' => 1, 'label' => 'bolt']);
        GizmoPart::create(['gizmo_id' => 1, 'label' => 'nut']);

        $_SERVER['REQUEST_URI'] = '/api/gizmo';
    }

    private function service(): GizmoService
    {
        return new GizmoService(new Gizmo());
    }

    public function testEagerLoadsARequestedRelation(): void
    {
        $results = $this->service()->where([], ['parts']);

        $first = $results->items()->first();

        $this->assertTrue($first->relationLoaded('parts'));
        $this->assertCount(2, $first->parts);
        $this->assertSame(2, $first->parts_count);
    }

    public function testAnUnknownRelationIsRejected(): void
    {
        $this->expectException(InvalidRelationException::class);

        $this->service()->where([], ['not_a_relation']);
    }

    public function testCountsMatchingRowsWithoutFetchingThem(): void
    {
        $service = $this->service();

        $this->assertSame(2, $service->getCountWhere([]));
        $this->assertSame(1, $service->getCountWhere([['type_id', '=', 2]]));
        $this->assertSame(0, $service->getCountWhere([['type_id', '=', 99]]));
    }

    public function testCountIssuesOneAggregateQuery(): void
    {
        $queries = [];
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->service()->getCountWhere([['type_id', '=', 1]]);

        $aggregates = array_values(array_filter($queries, fn ($sql) => str_contains($sql, 'count(*)')));

        $this->assertCount(1, $aggregates);
        $this->assertStringNotContainsString('limit', $aggregates[0]);
    }

    /**
     * select() used to read getTable() off whatever eagerLoad() returned. With any
     * relation requested that is a Builder, which forwards unknown calls to the
     * query builder, so asking for relations and selects together was a
     * BadMethodCallException.
     */
    public function testFindTakesRelationsAndSelectsTogether(): void
    {
        $gizmo = Gizmo::first();

        $found = $this->service()->find($gizmo->id, ['parts'], ['id', 'name']);

        $this->assertSame(['id', 'name', 'parts'], array_keys($found->toArray()));
        $this->assertCount(2, $found->parts);
    }

    public function testBareSelectsAreQualifiedWithTheTable(): void
    {
        $results = $this->withoutPhpErrors(fn () => $this->service()->where([], [], [], ['name']));

        $row = $results->items()->first()->toArray();

        $this->assertSame(['name'], array_keys($row));
    }

    public function testAlreadyQualifiedSelectsPassThrough(): void
    {
        $results = $this->withoutPhpErrors(fn () => $this->service()->where([], [], [], ['gizmos.name']));

        $row = $results->items()->first()->toArray();

        $this->assertSame(['name'], array_keys($row));
    }

    private function withoutPhpErrors(callable $fn)
    {
        $errors = [];
        set_error_handler(function ($number, $message) use (&$errors) {
            $errors[] = $message;
            return true;
        });

        try {
            $result = $fn();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $errors);

        return $result;
    }

    public function testAnUnmappedSortRaisesSortExceptionNotUndefinedProperty(): void
    {
        $errors = [];
        set_error_handler(function ($number, $message) use (&$errors) {
            $errors[] = $message;
            return true;
        });

        try {
            $this->service()->where([], [], [['not_a_column', 'ASC']]);
            $this->fail('expected a SortException');
        } catch (SortException $e) {
            $this->assertStringContainsString('not_a_column', $e->getMessage());
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $errors);
    }

    public function testSortingOnARealColumnStillWorks(): void
    {
        $results = $this->service()->where([], [], [['name', 'DESC']]);

        $this->assertSame(['beta', 'alpha'], $results->items()->pluck('name')->all());
    }

    public function testWhereStillWorksWithNoRelations(): void
    {
        $results = $this->service()->where([['type_id', '=', 2]]);

        $this->assertSame(1, $results->count());
        $this->assertSame('beta', $results->items()->first()->name);
    }
}
