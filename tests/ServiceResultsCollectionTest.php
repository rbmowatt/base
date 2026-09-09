<?php

namespace RBMowatt\BaseTests;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use RBMowatt\Base\Services\ServiceResultsCollection;

class ServiceResultsCollectionTest extends TestCase
{
    public function testAcceptsRawArrayResults(): void
    {
        $results = new ServiceResultsCollection(null, ['a', 'b']);

        $this->assertSame(['a', 'b'], $results->items());
        $this->assertSame(2, $results->count());
        $this->assertSame([], $results->getMeta());
    }

    public function testAcceptsAPaginator(): void
    {
        $paginator = new LengthAwarePaginator(['a', 'b'], 10, 2, 1);

        $results = new ServiceResultsCollection(null, $paginator);

        $this->assertSame(10, $results->getMeta('total'));
        $this->assertSame(2, $results->getMeta('per_page'));
        $this->assertSame(2, $results->count());
    }

    public function testAcceptsACollection(): void
    {
        $results = new ServiceResultsCollection(null, new Collection(['a']));

        $this->assertSame(1, $results->count());
        $this->assertSame([], $results->getMeta());
    }

    public function testConstructionRaisesNoDeprecations(): void
    {
        $errors = [];
        set_error_handler(function ($number, $message) use (&$errors) {
            $errors[] = $message;
            return true;
        });

        try {
            new ServiceResultsCollection(null, new Collection(['a']));
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $errors);
    }
}
