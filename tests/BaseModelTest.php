<?php

namespace RBMowatt\BaseTests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RBMowatt\Base\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use RBMowatt\Base\Models\Exceptions\ExtraneousDataException;
use RBMowatt\Base\Models\Exceptions\SoftDeletesNotEnabledException;
use RBMowatt\Base\Models\Exceptions\InvalidDateFormatException;

class Widget extends BaseModel
{
    protected $table = 'widgets';
}

/**
 * No $table on purpose: Eloquent derives "gadgets", but the $table property stays
 * null, which is what used to collapse every such model onto one cache key.
 */
class Gadget extends BaseModel
{
}

class Doodad extends BaseModel
{
}

class Trashable extends BaseModel
{
    use SoftDeletes;

    protected $table = 'trashables';

    protected $fillable = ['name'];
}

class BaseModelTest extends TestCase
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

        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('gadgets', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->integer('weight');
        });

        Schema::create('doodads', function (Blueprint $table) {
            $table->id();
            $table->string('colour');
        });

        Schema::create('trashables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function testModelsWithADerivedTableNameDoNotShareAColumnCache(): void
    {
        $this->assertSame(['id', 'label', 'weight'], (new Gadget())->columns());
        $this->assertSame(['id', 'colour'], (new Doodad())->columns());
        $this->assertSame(['id', 'label', 'weight'], (new Gadget())->columns());
    }

    public function testAnExplicitTableStillGetsItsOwnCache(): void
    {
        $this->assertSame(['id', 'name'], (new Widget())->columns());
        $this->assertSame(['id', 'label', 'weight'], (new Gadget())->columns());
        $this->assertSame(['id', 'name'], (new Widget())->columns());
    }

    public function testFilterKeepsOnlyRealColumns(): void
    {
        $filtered = (new Widget())->filter([
            'name' => 'a widget',
            'not_a_column' => 'dropped',
        ]);

        $this->assertSame(['name' => 'a widget'], $filtered);
    }

    public function testFilterOnAnEmptyPayload(): void
    {
        $this->assertSame([], (new Widget())->filter([]));
    }

    public function testSoftDeleteHidesTheRowAndKeepsItRestorable(): void
    {
        $row = Trashable::create(['name' => 'bin me']);

        $row->softDelete();

        $this->assertSame(0, Trashable::count());
        $this->assertSame(1, Trashable::onlyTrashed()->count());
        $this->assertNotNull(Trashable::withTrashed()->find($row->id)->deleted_at);

        Trashable::onlyTrashed()->first()->restore();

        $this->assertSame(1, Trashable::count());
    }

    public function testSoftDeleteFiresTheDeletingEvents(): void
    {
        $seen = [];
        Trashable::deleting(function () use (&$seen) { $seen[] = 'deleting'; });
        Trashable::deleted(function () use (&$seen) { $seen[] = 'deleted'; });

        Trashable::create(['name' => 'bin me'])->softDelete();

        $this->assertSame(['deleting', 'deleted'], $seen);

        Trashable::flushEventListeners();
    }

    public function testSoftDeleteRefusesAModelWithoutTheTrait(): void
    {
        $widget = new Widget();
        $widget->timestamps = false;
        $widget->name = 'stays put';
        $widget->save();

        try {
            $widget->softDelete();
            $this->fail('a model with no SoftDeletes trait was allowed to soft delete');
        } catch (SoftDeletesNotEnabledException $e) {
            $this->assertStringContainsString('SoftDeletes', $e->getMessage());
        }

        $this->assertSame(1, Widget::count());
    }

    public function testValidateAcceptsOnlyRealColumns(): void
    {
        $this->assertTrue((new Widget())->validate(['name' => 'a widget']));
        $this->assertTrue((new Widget())->validate([]));
    }

    public function testValidateRejectsAPayloadMixingRealAndJunkKeys(): void
    {
        $this->expectException(ExtraneousDataException::class);
        $this->expectExceptionMessage('bogus');

        (new Widget())->validate(['name' => 'a widget', 'bogus' => 1]);
    }

    public function testValidateRejectsAWhollyJunkPayload(): void
    {
        $this->expectException(ExtraneousDataException::class);

        (new Widget())->validate(['bogus' => 1]);
    }

    public function testRelativeDatesAreResolved(): void
    {
        $this->assertSame(
            date('Y-m-d 00:00:00', strtotime('-3 days')),
            $this->calculateSince('3_days')
        );
    }

    public function testTimestampsAndDateStringsAreNormalized(): void
    {
        $this->assertSame(
            date(Widget::STANDARD_DATE_FORMAT, 1700000000),
            $this->calculateSince('1700000000')
        );
        $this->assertSame('2026-01-02 00:00:00', $this->calculateSince('2026-01-02'));
    }

    public function testAnUnparseableDateThrowsTheRightException(): void
    {
        $this->expectException(InvalidDateFormatException::class);

        $this->calculateSince('sometime last tuesday');
    }

    public function testAMalformedRelativeDateThrowsTheRightException(): void
    {
        $this->expectException(InvalidDateFormatException::class);

        $this->calculateSince('3_fortnights');
    }

    private function calculateSince(string $date)
    {
        $method = new \ReflectionMethod(Widget::class, 'calculateSinceData');
        $method->setAccessible(true);

        return $method->invoke(new Widget(), $date);
    }
}
