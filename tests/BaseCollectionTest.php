<?php

namespace RBMowatt\BaseTests;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use RBMowatt\Base\Models\BaseCollection;

class ArrayableThing implements Arrayable
{
    public function toArray()
    {
        return ['from' => 'arrayable'];
    }
}

class JsonableThing implements Jsonable
{
    public function toJson($options = 0)
    {
        return json_encode(['from' => 'jsonable']);
    }
}

class BaseCollectionTest extends TestCase
{
    public function testJsonableAndArrayableItemsAreUnwrapped(): void
    {
        $serialized = (new BaseCollection([
            new JsonableThing(),
            new ArrayableThing(),
            'plain',
        ]))->jsonSerialize();

        $this->assertSame([
            ['from' => 'jsonable'],
            ['from' => 'arrayable'],
            'plain',
        ], $serialized);
    }

    public function testGetValuesByKey(): void
    {
        $collection = new BaseCollection([
            (object) ['id' => 1],
            (object) ['id' => 3],
        ]);

        $this->assertSame([1, 3], $collection->getValuesByKey('id'));
    }
}
