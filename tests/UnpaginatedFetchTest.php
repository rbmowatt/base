<?php

namespace RBMowatt\BaseTests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Models\BaseModel;
use RBMowatt\Base\Services\BaseService;
use RBMowatt\Base\Services\Exceptions\InvalidQueryParamException;
use RBMowatt\Base\Services\Exceptions\UnboundedResultException;
use RBMowatt\Base\Services\ServiceResultsCollection;

class Region extends BaseModel
{
    protected $table = 'regions';
    public $timestamps = false;
}

class RegionService extends BaseService
{
    protected $filterable = ['active'];

    protected $sortable = ['name'];

    protected $maxUnpaginated = 5;

    public function __construct()
    {
        $this->primaryModel = new Region();
    }
}

class UnpaginatedFetchTest extends TestCase
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

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
        });
    }

    private function makeRegions(int $count, bool $active = true): void
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['name' => 'region ' . str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'active' => $active];
        }
        Region::insert($rows);
    }

    public function testReturnsEveryRowWithoutPaginationMetadata(): void
    {
        $this->makeRegions(4);

        $results = (new RegionService())->all();

        $this->assertInstanceOf(ServiceResultsCollection::class, $results);
        $this->assertSame(4, $results->count());
        $this->assertSame([], $results->getMeta());
    }

    public function testSittingExactlyOnTheCeilingIsFine(): void
    {
        $this->makeRegions(5);

        $this->assertSame(5, (new RegionService())->all()->count());
    }

    public function testGoingOverTheCeilingThrowsRatherThanTruncating(): void
    {
        // the failure mode this guards: a caller shipping 5 of 6 rows as if it were
        // the whole list
        $this->makeRegions(6);

        $this->expectException(UnboundedResultException::class);

        (new RegionService())->all();
    }

    public function testTheCeilingIsReportedOnTheException(): void
    {
        $this->makeRegions(6);

        try {
            (new RegionService())->all();
            $this->fail('expected UnboundedResultException');
        } catch (UnboundedResultException $e) {
            $this->assertSame(5, $e->getCeiling());
            $this->assertStringContainsString('5', $e->getMessage());
            $this->assertStringNotContainsString(Region::class, $e->getMessage());
        }
    }

    public function testFiltersNarrowTheResultAndKeepItUnderTheCeiling(): void
    {
        $this->makeRegions(6, false);
        $this->makeRegions(2, true);

        $results = (new RegionService())->all([['active', '=', 1]]);

        $this->assertSame(2, $results->count());
    }

    public function testFiltersGoThroughTheSameAllowlist(): void
    {
        $this->makeRegions(1);

        $this->expectException(InvalidQueryParamException::class);

        (new RegionService())->all([['name', '=', 'region 001']]);
    }

    public function testSortsApply(): void
    {
        Region::insert([
            ['name' => 'zulu', 'active' => true],
            ['name' => 'alpha', 'active' => true],
        ]);

        $results = (new RegionService())->all([], [], [['name', 'ASC']]);

        $this->assertSame('alpha', $results->items()->first()->name);
    }

    public function testTheDefaultCeilingIsNotUnbounded(): void
    {
        $service = new class extends BaseService {
            public function __construct()
            {
                $this->primaryModel = new Region();
            }

            public function ceiling()
            {
                return $this->maxUnpaginated;
            }
        };

        $this->assertSame(500, $service->ceiling());
    }
}
