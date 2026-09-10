<?php

namespace RBMowatt\BaseTests;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Models\BaseModel;
use RBMowatt\Base\Services\BaseService;
use RBMowatt\Base\Services\Exceptions\InvalidRelationException;

class Vault extends BaseModel
{
    protected $table = 'vaults';
    public $timestamps = false;

    public function keys(): HasMany
    {
        return $this->hasMany(VaultKey::class, 'vault_id');
    }
}

class VaultKey extends BaseModel
{
    protected $table = 'vault_keys';
    public $timestamps = false;

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class, 'vault_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(VaultAudit::class, 'vault_key_id');
    }
}

class VaultAudit extends BaseModel
{
    protected $table = 'vault_audits';
    public $timestamps = false;
}

class VaultService extends BaseService
{
    public function __construct()
    {
        $this->primaryModel = new Vault();
    }
}

class RelationTraversalTest extends TestCase
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

        Schema::create('vaults', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('vault_keys', function (Blueprint $table) {
            $table->id();
            $table->integer('vault_id');
            $table->string('secret');
        });
        Schema::create('vault_audits', function (Blueprint $table) {
            $table->id();
            $table->integer('vault_key_id');
            $table->string('action');
        });

        Vault::insert([['name' => 'main']]);
        VaultKey::insert([['vault_id' => 1, 'secret' => 'sk_live_1234567890']]);
        VaultAudit::insert([['vault_key_id' => 1, 'action' => 'read']]);
    }

    public function testADeclaredRootStillLoads(): void
    {
        $results = (new VaultService())->where([], ['keys']);

        $this->assertTrue($results->items()->first()->relationLoaded('keys'));
    }

    public function testEverySegmentOfANestedPathIsChecked(): void
    {
        $results = (new VaultService())->where([], ['keys.audits']);

        $first = $results->items()->first();
        $this->assertTrue($first->keys->first()->relationLoaded('audits'));
    }

    public function testAnUnknownSegmentBelowAValidRootIsRejected(): void
    {
        // this is the hole: `keys` resolves, so the old check passed the whole path
        // through to Eloquent and never looked at what came after it
        $this->expectException(InvalidRelationException::class);

        (new VaultService())->where([], ['keys.not_a_relation']);
    }

    public function testAnUnknownSegmentTwoLevelsDownIsRejected(): void
    {
        $this->expectException(InvalidRelationException::class);

        (new VaultService())->where([], ['keys.audits.whatever']);
    }

    public function testTheRejectionNamesTheWholePathNotJustTheRoot(): void
    {
        try {
            (new VaultService())->where([], ['keys.not_a_relation']);
            $this->fail('expected InvalidRelationException');
        } catch (InvalidRelationException $e) {
            $this->assertStringContainsString('keys.not_a_relation', $e->getMessage());
        }
    }

    public function testDepthIsCapped(): void
    {
        $service = new class extends BaseService {
            protected $maxRelationDepth = 2;

            public function __construct()
            {
                $this->primaryModel = new Vault();
            }
        };

        $this->expectException(InvalidRelationException::class);

        // every segment resolves; the path is simply longer than the service allows
        $service->where([], ['keys.vault.keys']);
    }

    public function testAnUnknownRootIsStillRejected(): void
    {
        $this->expectException(InvalidRelationException::class);

        (new VaultService())->where([], ['not_a_relation']);
    }
}
