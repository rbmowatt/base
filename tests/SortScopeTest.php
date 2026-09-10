<?php

namespace RBMowatt\BaseTests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Models\BaseModel;
use RBMowatt\Base\Rest\Query\QueryParser;
use RBMowatt\Base\Services\BaseService;
use RBMowatt\Base\Services\Exceptions\SortException;

class Crate extends BaseModel
{
    protected $table = 'crates';
    public $timestamps = false;

    public function scopeSortByWeightClass($query, $key, $direction)
    {
        return $query->orderBy('weight', $direction);
    }
}

class CrateService extends BaseService
{
    protected $sortable = ['label'];

    protected $sortScopes = ['weight_class' => 'sortByWeightClass'];

    public function __construct()
    {
        $this->primaryModel = new Crate();
    }
}

class DottedSortScopeService extends BaseService
{
    protected $sortScopes = ['weight.class' => 'sortByWeightClass'];

    public function __construct()
    {
        $this->primaryModel = new Crate();
    }
}

class SortScopeTest extends TestCase
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

        Schema::create('crates', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->integer('weight');
        });

        Crate::insert([
            ['label' => 'zulu', 'weight' => 1],
            ['label' => 'alpha', 'weight' => 9],
        ]);
    }

    public function testASortScopeOrdersBySomethingTheKeyDoesNotName(): void
    {
        $results = (new CrateService())->where([], [], [['weight_class', 'DESC']]);

        $this->assertSame(['alpha', 'zulu'], $results->items()->pluck('label')->all());
    }

    public function testTheScopeReceivesTheKeyThenTheDirection(): void
    {
        $seen = [];
        $model = new class($seen) extends Crate {
            public static $args = [];

            public function __construct($ignored = null, array $attributes = [])
            {
                parent::__construct($attributes);
            }

            public function scopeSortByWeightClass($query, $key, $direction)
            {
                self::$args = [$key, $direction];
                return $query->orderBy('weight', $direction);
            }
        };

        $service = new class extends BaseService {
            protected $sortScopes = ['weight_class' => 'sortByWeightClass'];
        };
        $service->setModel($model);
        $service->where([], [], [['weight_class', 'ASC']]);

        $this->assertSame(['weight_class', 'ASC'], $model::$args);
    }

    public function testAnUndeclaredSortKeyIsRejected(): void
    {
        $this->expectException(SortException::class);

        (new CrateService())->where([], [], [['not_declared', 'ASC']]);
    }

    public function testSortScopeKeysAreMatchedExactly(): void
    {
        // ?sort=weight_class_ASC arrives as the key weight_class. Unlike $scopes,
        // getSortScope() does no underscore-to-dot conversion, so a dotted sortScope
        // key is unreachable from a query string.
        $this->expectException(SortException::class);

        (new DottedSortScopeService())->where([], [], [['weight_class', 'ASC']]);
    }

    public function testTheParserSplitsTheDirectionOffTheLastUnderscore(): void
    {
        $parser = new QueryParser(Request::create('/api/crate', 'GET', ['sort' => 'weight_class_ASC']));

        $this->assertSame([['weight_class', 'ASC']], $parser->getSorts());
    }

    public function testAnAllowlistedColumnStillSortsDirectly(): void
    {
        $results = (new CrateService())->where([], [], [['label', 'ASC']]);

        $this->assertSame(['alpha', 'zulu'], $results->items()->pluck('label')->all());
    }
}
