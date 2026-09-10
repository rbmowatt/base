<?php

namespace RBMowatt\BaseTests;

use Illuminate\Http\Request;
use RBMowatt\Base\Rest\Query\QueryParser;
use RBMowatt\Base\Services\Exceptions\SortException;

class LimitClampTest extends TestCase
{
    private function parser(array $query): QueryParser
    {
        return new QueryParser(Request::create('/api/thing', 'GET', $query));
    }

    public function testAnAbsentLimitIsTheDefault(): void
    {
        $this->assertSame(QueryParser::DEFAULT_LIMIT, $this->parser([])->getLimit());
    }

    public function testAReasonableLimitPassesThroughAsAnInt(): void
    {
        $limit = $this->parser(['limit' => '50'])->getLimit();

        $this->assertSame(50, $limit);
    }

    public function testAHugeLimitIsClampedRatherThanDumpingTheTable(): void
    {
        $this->assertSame(QueryParser::MAX_LIMIT, $this->parser(['limit' => '1000000'])->getLimit());
    }

    public function testANegativeLimitFloorsAtOne(): void
    {
        $this->assertSame(1, $this->parser(['limit' => '-1'])->getLimit());
    }

    public function testANonNumericLimitFallsBackToTheDefault(): void
    {
        // (int) 'abc' is 0, which would page by nothing
        $this->assertSame(QueryParser::DEFAULT_LIMIT, $this->parser(['limit' => 'abc'])->getLimit());
    }

    public function testAnArrayLimitDoesNotBlowUp(): void
    {
        $this->assertSame(QueryParser::DEFAULT_LIMIT, $this->parser(['limit' => ['1', '2']])->getLimit());
    }

    public function testPageIsAnIntAndFloorsAtOne(): void
    {
        $this->assertSame(1, $this->parser(['page' => '0'])->getPage());
        $this->assertSame(1, $this->parser(['page' => '-7'])->getPage());
        $this->assertSame(3, $this->parser(['page' => '3'])->getPage());
        $this->assertSame(QueryParser::DEFAULT_PAGE, $this->parser(['page' => 'abc'])->getPage());
    }

    public function testTheCeilingCanBeRaisedBySubclassing(): void
    {
        $parser = new class(Request::create('/api/thing', 'GET', ['limit' => '5000'])) extends QueryParser {
            protected $maxLimit = 5000;
        };

        $this->assertSame(5000, $parser->getLimit());
    }

    public function testABadSortOrderStaysAReadableClientError(): void
    {
        // a bare \Exception here would be redacted to 'Server Error' by ApiResponse,
        // which is the wrong answer for a caller who simply typed the key wrong
        $this->expectException(SortException::class);
        $this->expectExceptionMessage('Invalid Sort Order');

        $this->parser(['sort' => 'name_SIDEWAYS'])->getSorts();
    }
}
