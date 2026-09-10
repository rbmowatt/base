<?php

namespace RBMowatt\BaseTests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Models\BaseModel;
use RBMowatt\Base\Services\BaseService;
use RBMowatt\Base\Services\Exceptions\AmbiguousQueryParamException;

class Ticket extends BaseModel
{
    protected $table = 'tickets';
    public $timestamps = false;

    public function scopeByQueueId($query, $value, $operator = '=')
    {
        return $query->where('queue_id', $operator, $value);
    }

    public function scopeByOwner($query, $value, $operator = '=')
    {
        return $query->where('owner', $operator, $value);
    }

    public function scopeSortByOwner($query, $key, $direction)
    {
        return $query->orderBy('owner', $direction);
    }

    public function scopeSortByQueueId($query, $key, $direction)
    {
        return $query->orderBy('queue_id', $direction);
    }
}

class CollidingService extends BaseService
{
    // queue.id arrives as ?queue_id=, which is also a real column
    protected $scopes = ['queue.id' => 'byQueueId'];

    public function __construct()
    {
        $this->primaryModel = new Ticket();
    }
}

class ResolvedService extends BaseService
{
    // same collision, settled by naming the column explicitly
    protected $filterable = ['queue_id'];

    protected $scopes = ['queue.id' => 'byQueueId'];

    public function __construct()
    {
        $this->primaryModel = new Ticket();
    }
}

class CollidingSortService extends BaseService
{
    // owner is a real column and the sort scope is named after it
    protected $sortScopes = ['owner' => 'sortByOwner'];

    public function __construct()
    {
        $this->primaryModel = new Ticket();
    }
}

class DottedCollidingSortService extends BaseService
{
    // queue.id maps to queue_id, which is also a column
    protected $sortScopes = ['queue.id' => 'sortByQueueId'];

    public function __construct()
    {
        $this->primaryModel = new Ticket();
    }
}

class ResolvedSortService extends BaseService
{
    protected $sortable = ['queue_id'];

    protected $sortScopes = ['queue.id' => 'sortByQueueId'];

    public function __construct()
    {
        $this->primaryModel = new Ticket();
    }
}

class DirectCollisionService extends BaseService
{
    // no underscore trickery needed: the scope key IS a column name
    protected $scopes = ['owner' => 'byOwner'];

    public function __construct()
    {
        $this->primaryModel = new Ticket();
    }
}

class ScopeCollisionTest extends TestCase
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

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->integer('queue_id');
            $table->string('owner');
        });

        Ticket::insert([
            ['queue_id' => 1, 'owner' => 'alice'],
            ['queue_id' => 2, 'owner' => 'bob'],
        ]);
    }

    public function testAKeyThatIsBothAColumnAndAScopeIsRejected(): void
    {
        // silently preferring either reading hides the ambiguity from the caller
        $this->expectException(AmbiguousQueryParamException::class);

        (new CollidingService())->where([['queue_id', '=', 1]]);
    }

    public function testTheMessageNamesBothReadings(): void
    {
        try {
            (new CollidingService())->where([['queue_id', '=', 1]]);
            $this->fail('expected AmbiguousQueryParamException');
        } catch (AmbiguousQueryParamException $e) {
            $this->assertStringContainsString('queue_id', $e->getMessage());
            $this->assertStringContainsString('queue.id', $e->getMessage());
            $this->assertSame('queue.id', $e->getScopeKey());
        }
    }

    public function testAnExactNameCollisionIsRejectedToo(): void
    {
        $this->expectException(AmbiguousQueryParamException::class);

        (new DirectCollisionService())->where([['owner', '=', 'alice']]);
    }

    public function testListingTheColumnSettlesIt(): void
    {
        $results = (new ResolvedService())->where([['queue_id', '=', 1]]);

        $this->assertSame(1, $results->count());
    }

    public function testAScopeWithNoMatchingColumnIsUnaffected(): void
    {
        $service = new class extends BaseService {
            protected $scopes = ['queue.number' => 'byQueueId'];

            public function __construct()
            {
                $this->primaryModel = new Ticket();
            }
        };

        $this->assertSame(1, $service->where([['queue_number', '=', 1]])->count());
    }

    public function testTheDottedFormStillReachesTheScopeDirectly(): void
    {
        // ?where=[queue.id=1] never looks like a column, so it is not ambiguous
        $this->assertSame(1, (new CollidingService())->where([['queue.id', '=', 1]])->count());
    }

    public function testASortKeyThatIsBothAColumnAndAScopeIsRejected(): void
    {
        $this->expectException(AmbiguousQueryParamException::class);

        (new CollidingSortService())->where([], [], [['owner', 'ASC']]);
    }

    public function testADottedSortScopeCollidingWithAColumnIsRejected(): void
    {
        $this->expectException(AmbiguousQueryParamException::class);

        (new DottedCollidingSortService())->where([], [], [['queue_id', 'ASC']]);
    }

    public function testTheSortMessagePointsAtSortableNotFilterable(): void
    {
        try {
            (new CollidingSortService())->where([], [], [['owner', 'ASC']]);
            $this->fail('expected AmbiguousQueryParamException');
        } catch (AmbiguousQueryParamException $e) {
            $this->assertSame('sortable', $e->getAllowlist());
            $this->assertStringContainsString('$sortable', $e->getMessage());
            $this->assertStringNotContainsString('$filterable', $e->getMessage());
        }
    }

    public function testListingTheColumnInSortableSettlesIt(): void
    {
        $results = (new ResolvedSortService())->where([], [], [['queue_id', 'DESC']]);

        $this->assertSame(2, $results->items()->first()->queue_id);
    }

    public function testASortScopeWithNoMatchingColumnIsUnaffected(): void
    {
        $service = new class extends BaseService {
            protected $sortScopes = ['queue.rank' => 'sortByQueueId'];

            public function __construct()
            {
                $this->primaryModel = new Ticket();
            }
        };

        $results = $service->where([], [], [['queue_rank', 'DESC']]);

        $this->assertSame(2, $results->items()->first()->queue_id);
    }
}
