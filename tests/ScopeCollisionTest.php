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
}
