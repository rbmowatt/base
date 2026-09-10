<?php

namespace RBMowatt\BaseTests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Models\BaseModel;
use RBMowatt\Base\Services\BaseService;
use RBMowatt\Base\Services\Exceptions\InvalidQueryParamException;
use RBMowatt\Base\Services\Exceptions\SortException;

class Member extends BaseModel
{
    protected $table = 'members';

    protected $fillable = ['name'];

    protected $hidden = ['password_hash'];

    public $timestamps = false;

    public function scopeByTier($query, $value, $operator = '=')
    {
        return $query->where('tier', $operator, $value);
    }
}

class MemberService extends BaseService
{
    protected $filterable = ['name'];

    protected $sortable = ['name'];

    protected $scopes = ['member.tier' => 'byTier'];
}

class FilterAllowlistTest extends TestCase
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

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('password_hash');
            $table->string('reset_token')->nullable();
            $table->integer('tier')->default(1);
        });

        Member::insert([
            ['name' => 'alice', 'password_hash' => '$2y$10$Kq7wZm', 'reset_token' => 'r-9f3a1c', 'tier' => 2],
            ['name' => 'bob', 'password_hash' => '$2y$10$Aa1bCd', 'reset_token' => null, 'tier' => 1],
        ]);
    }

    private function service(): MemberService
    {
        $service = new MemberService();
        $service->setModel(new Member());

        return $service;
    }

    public function testAnAllowlistedColumnFilters(): void
    {
        $results = $this->service()->where([['name', '=', 'alice']]);

        $this->assertSame(1, $results->count());
    }

    public function testAColumnThatIsRealButNotListedIsRejected(): void
    {
        $this->expectException(InvalidQueryParamException::class);

        $this->service()->where([['reset_token', '=', 'r-9f3a1c']]);
    }

    public function testTheComparisonOracleIsClosed(): void
    {
        // the shape of the attack: ?password_hash=>$2y$10$K answered by ?count=true
        $this->expectException(InvalidQueryParamException::class);

        $this->service()->getCountWhere([['password_hash', '>', '$2y$10$K']]);
    }

    public function testTheRejectionDoesNotConfirmTheColumnExists(): void
    {
        // a real-but-unlisted column and pure nonsense have to look identical,
        // otherwise the error itself enumerates the schema
        $real = null;
        $fake = null;

        try {
            $this->service()->where([['password_hash', '=', 'x']]);
        } catch (InvalidQueryParamException $e) {
            $real = str_replace('password_hash', 'KEY', $e->getMessage());
        }

        try {
            $this->service()->where([['not_a_column_at_all', '=', 'x']]);
        } catch (InvalidQueryParamException $e) {
            $fake = str_replace('not_a_column_at_all', 'KEY', $e->getMessage());
        }

        $this->assertNotNull($real);
        $this->assertSame($real, $fake);
    }

    public function testADeclaredScopeStillResolves(): void
    {
        // arrives as ?member_tier=2 and maps back to the dotted scope key
        $results = $this->service()->where([['member_tier', '=', 2]]);

        $this->assertSame(1, $results->count());
        $this->assertSame('alice', $results->items()->first()->name);
    }

    public function testAServiceThatDeclaresNothingFiltersNothing(): void
    {
        $service = new class extends BaseService {
            public function __construct()
            {
                $this->primaryModel = new Member();
            }
        };

        $this->expectException(InvalidQueryParamException::class);

        $service->where([['name', '=', 'alice']]);
    }

    public function testSortingIsAllowlistedToo(): void
    {
        $results = $this->service()->where([], [], [['name', 'ASC']]);

        $this->assertSame('alice', $results->items()->first()->name);
    }

    public function testSortingOnAnUnlistedColumnIsRejected(): void
    {
        $this->expectException(SortException::class);

        $this->service()->where([], [], [['password_hash', 'ASC']]);
    }
}
