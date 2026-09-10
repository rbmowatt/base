<?php

namespace RBMowatt\BaseTests;

use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Models\BaseModel;
use RBMowatt\Base\Services\BaseService;

class Guarded extends BaseModel
{
    protected $table = 'guarded_rows';

    protected $fillable = ['name'];

    public $timestamps = false;
}

class GuardedService extends BaseService
{
    public function __construct(Guarded $model)
    {
        $this->primaryModel = $model;
    }
}

class MassAssignmentTest extends TestCase
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

        Schema::create('guarded_rows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_admin')->default(false);
        });

        Guarded::create(['name' => 'alice']);
    }

    private function service(): GuardedService
    {
        return new GuardedService(new Guarded());
    }

    public function testCreateRejectsAColumnThatIsNotFillable(): void
    {
        // is_admin is a real column, so a column-existence check alone would let it
        // through straight off the request
        $this->expectException(MassAssignmentException::class);
        $this->expectExceptionMessage('is_admin');

        $this->service()->create(['name' => 'mallory', 'is_admin' => 1]);
    }

    public function testCreateStillAcceptsFillableColumns(): void
    {
        $row = $this->service()->create(['name' => 'bob']);

        $this->assertSame('bob', $row->fresh()->name);
    }

    public function testUpdateRejectsAColumnThatIsNotFillable(): void
    {
        $victim = Guarded::where('name', 'alice')->first();

        $this->expectException(MassAssignmentException::class);
        $this->expectExceptionMessage('is_admin');

        $this->service()->update($victim->id, ['is_admin' => 1]);
    }

    public function testUpdateLeavesTheRowUntouchedWhenItRejects(): void
    {
        $victim = Guarded::where('name', 'alice')->first();

        try {
            $this->service()->update($victim->id, ['name' => 'renamed', 'is_admin' => 1]);
        } catch (MassAssignmentException $e) {
            // guard runs before fill(), so nothing is written
        }

        $fresh = Guarded::find($victim->id);
        $this->assertSame('alice', $fresh->name);
        $this->assertSame(0, (int) $fresh->is_admin);
    }

    public function testUpdateStillAcceptsFillableColumns(): void
    {
        $victim = Guarded::where('name', 'alice')->first();

        $this->service()->update($victim->id, ['name' => 'renamed']);

        $this->assertSame('renamed', Guarded::find($victim->id)->name);
    }

    public function testAModelThatDeclaresNothingIsTotallyGuarded(): void
    {
        // Eloquent's own default is $guarded = ['*'], and the service now honors it
        // rather than writing every column the table happens to have.
        $model = new class extends BaseModel {
            protected $table = 'guarded_rows';
            public $timestamps = false;
        };

        $service = new class($model) extends BaseService {
            public function __construct($model)
            {
                $this->primaryModel = $model;
            }
        };

        $this->expectException(MassAssignmentException::class);

        $service->create(['name' => 'nope']);
    }
}
