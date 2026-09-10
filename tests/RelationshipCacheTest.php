<?php

namespace RBMowatt\BaseTests;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use RBMowatt\Base\Models\BaseModel;

class CountedChild extends BaseModel
{
    protected $table = 'counted_children';
    public $timestamps = false;
}

class CountedParent extends BaseModel
{
    public static $discoveries = 0;

    protected $table = 'counted_parents';
    public $timestamps = false;

    public function children(): HasMany
    {
        // only reached while the map is actually being built
        self::$discoveries++;

        return $this->hasMany(CountedChild::class, 'counted_parent_id');
    }
}

class RelationshipCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CountedParent::$discoveries = 0;
        Cache::flush();
    }

    public function testDiscoveryRunsOnceAcrossRepeatedCalls(): void
    {
        $model = new CountedParent();

        for ($i = 0; $i < 25; $i++) {
            $model->relationships();
        }

        $this->assertSame(1, CountedParent::$discoveries);
    }

    public function testDiscoveryRunsOnceAcrossSeparateInstances(): void
    {
        // the cache is keyed by class, not by object, which is what makes it useful
        // across requests rather than only within one
        (new CountedParent())->relationships();
        (new CountedParent())->relationships();
        (new CountedParent())->relationships();

        $this->assertSame(1, CountedParent::$discoveries);
    }

    public function testTheCachedMapIsStillCorrect(): void
    {
        $first = (new CountedParent())->relationships();
        $second = (new CountedParent())->relationships();

        $this->assertSame($first, $second);
        $this->assertArrayHasKey('children', $second);
        $this->assertSame(CountedChild::class, $second['children']['model']);
        $this->assertSame('HasMany', $second['children']['type']);
        // HasMany reports the foreign key already qualified with the child's table
        $this->assertSame('counted_children.counted_parent_id', $second['children']['fk']);
    }

    public function testFlushingTheCacheForcesRediscovery(): void
    {
        (new CountedParent())->relationships();
        Cache::flush();
        (new CountedParent())->relationships();

        $this->assertSame(2, CountedParent::$discoveries);
    }

    public function testTwoModelsDoNotShareACacheEntry(): void
    {
        $parent = (new CountedParent())->relationships();
        $child = (new CountedChild())->relationships();

        $this->assertArrayHasKey('children', $parent);
        $this->assertSame([], $child);
    }

    public function testAnAnonymousModelDoesNotBreakTheCacheKey(): void
    {
        // an anonymous class name carries a NUL byte and the defining file path,
        // which is not a legal cache key on every store
        $model = new class extends BaseModel {
            protected $table = 'counted_children';
            public $timestamps = false;

            public function kids(): HasMany
            {
                return $this->hasMany(CountedChild::class, 'counted_parent_id');
            }
        };

        $this->assertArrayHasKey('kids', $model->relationships());
        $this->assertArrayHasKey('kids', $model->relationships());
    }
}
