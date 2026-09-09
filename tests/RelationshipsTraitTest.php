<?php

namespace RBMowatt\BaseTests;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Models\BaseModel;

class Invoice extends BaseModel
{
    protected $table = 'invoices';

    public static $sideEffects = 0;

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'invoice_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function maybeLines(): ?HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'invoice_id');
    }

    /**
     * A real relation with no declared return type. Deliberately here: the scan
     * cannot see it, and the test below pins that as known behavior.
     */
    public function untypedLines()
    {
        return $this->hasMany(InvoiceLine::class, 'invoice_id');
    }

    public function archive()
    {
        self::$sideEffects++;

        return true;
    }

    public function total(): int
    {
        self::$sideEffects++;

        return 0;
    }
}

class InvoiceLine extends BaseModel
{
    protected $table = 'invoice_lines';
}

class Client extends BaseModel
{
    protected $table = 'clients';
}

class RelationshipsTraitTest extends TestCase
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

        Invoice::$sideEffects = 0;

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->integer('client_id');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->integer('invoice_id');
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
        });
    }

    public function testSideEffectingMethodsAreNeverCalled(): void
    {
        (new Invoice())->relationships();

        $this->assertSame(0, Invoice::$sideEffects);
    }

    public function testTypedRelationsAreFoundWithTheirKeyAndModel(): void
    {
        $relations = (new Invoice())->relationships();

        $this->assertSame(['lines', 'client', 'maybeLines'], array_keys($relations));

        $this->assertSame('HasMany', $relations['lines']['type']);
        $this->assertSame('invoice_lines.invoice_id', $relations['lines']['fk']);
        $this->assertSame(InvoiceLine::class, $relations['lines']['model']);

        $this->assertSame('BelongsTo', $relations['client']['type']);
        $this->assertSame(Client::class, $relations['client']['model']);
    }

    public function testAnUntypedRelationIsNotFound(): void
    {
        $this->assertArrayNotHasKey('untypedLines', (new Invoice())->relationships());
    }

    public function testDiscoveryRunsNoQueries(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        (new Invoice())->relationships();

        $this->assertSame(0, $queries);
    }

    public function testGetFkAndGetRelationshipModelStillWork(): void
    {
        $invoice = new Invoice();

        $this->assertSame('invoice_lines.invoice_id', $invoice->getFk('lines'));
        $this->assertInstanceOf(InvoiceLine::class, $invoice->getRelationshipModel('lines'));
    }
}
