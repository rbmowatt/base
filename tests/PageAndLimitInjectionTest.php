<?php

namespace RBMowatt\BaseTests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Models\BaseModel;
use RBMowatt\Base\Models\Exceptions\InvalidArgumentsException;
use RBMowatt\Base\Services\BaseService;

class Reading extends BaseModel
{
    protected $table = 'readings';

    public $timestamps = false;
}

class ReadingService extends BaseService
{
    // the wiring that makes the scope request-reachable
    protected $scopes = ['group' => 'limitTo'];

    public function __construct()
    {
        $this->primaryModel = new Reading();
    }
}

class PageAndLimitInjectionTest extends TestCase
{
    private const PAYLOAD = '1) UNION SELECT password_hash,1,1 FROM readings -- ';

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

        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->integer('patient_id');
            $table->string('password_hash');
        });
    }

    public function testLimitToRejectsAGroupThatIsNotAColumn(): void
    {
        $this->expectException(InvalidArgumentsException::class);

        Reading::query()->limitTo(self::PAYLOAD, 10)->toSql();
    }

    public function testPageRejectsAGroupThatIsNotAColumn(): void
    {
        $this->expectException(InvalidArgumentsException::class);

        Reading::query()->page(self::PAYLOAD, 1, 10)->toSql();
    }

    public function testPtRejectsAGroupThatIsNotAColumn(): void
    {
        $this->expectException(InvalidArgumentsException::class);

        Reading::query()->pt(self::PAYLOAD, 1, 10)->toSql();
    }

    public function testARealColumnIsQuotedIntoTheStatement(): void
    {
        $sql = Reading::query()->limitTo('patient_id', 10)->toSql();

        $this->assertStringContainsString('"readings"."patient_id"', $sql);
        $this->assertStringNotContainsString('UNION', $sql);
    }

    public function testNoPayloadSurvivesIntoTheStatement(): void
    {
        try {
            $sql = Reading::query()->limitTo(self::PAYLOAD, 10)->toSql();
        } catch (InvalidArgumentsException $e) {
            $sql = '';
        }

        $this->assertStringNotContainsString('UNION', $sql);
        $this->assertStringNotContainsString('--', $sql);
    }

    public function testTheRequestReachablePathIsClosed(): void
    {
        // ?group=<payload> against a service that maps the scope
        $this->expectException(InvalidArgumentsException::class);

        (new ReadingService())->where([['group', '=', self::PAYLOAD]]);
    }

    public function testAnOperatorIsNotAValidGroupSize(): void
    {
        // setWheres() passes (value, operator), so '=' lands where $n is expected
        $this->expectException(InvalidArgumentsException::class);

        Reading::query()->limitTo('patient_id', '=');
    }

    public function testAZeroOrNegativeGroupSizeIsRejected(): void
    {
        $this->expectException(InvalidArgumentsException::class);

        Reading::query()->limitTo('patient_id', 0);
    }
}
