<?php

namespace RBMowatt\BaseTests;

use Illuminate\Http\Request;
use RBMowatt\Base\Rest\Exceptions\MissingParameterException;
use RBMowatt\Base\Rest\Query\QueryParser;

class QueryParserTest extends TestCase
{
    private function parser(array $query): QueryParser
    {
        return new QueryParser(Request::create('/things', 'GET', $query));
    }

    public function testBareQueryKeysBecomeEqualityWheres(): void
    {
        $wheres = $this->parser(['type_id' => '1'])->getWheres();

        $this->assertSame([['type_id', '=', '1']], $wheres);
    }

    public function testReservedKeysAreNotTreatedAsWheres(): void
    {
        $wheres = $this->parser([
            'limit' => '5',
            'page' => '2',
            'sort' => 'name_ASC',
            'with' => 'owner',
            'select' => 'id',
            'count' => 'true',
            'type_id' => '1',
        ])->getWheres();

        $this->assertSame([['type_id', '=', '1']], $wheres);
    }

    public function testWithoutListExcludesAdditionalKeys(): void
    {
        $wheres = $this->parser(['type_id' => '1', 'token' => 'abc'])->getWheres([], ['token']);

        $this->assertSame([['type_id', '=', '1']], $wheres);
    }

    public function testBracketedWhereStringIsParsed(): void
    {
        $wheres = $this->parser(['where' => '[type_id=1,manager.id!=139707]'])->getWheres();

        $this->assertContains(['type_id', '=', '1'], $wheres);
        $this->assertContains(['manager.id', '!=', '139707'], $wheres);
    }

    public function testGreaterAndLessThanAreLiftedOutOfTheValue(): void
    {
        $wheres = $this->parser(['where' => '[age=>21,score=<100]'])->getWheres();

        $this->assertContains(['age', '>', '21'], $wheres);
        $this->assertContains(['score', '<', '100'], $wheres);
    }

    public function testWithIsParsedFromBothQueryShapes(): void
    {
        $this->assertSame(['owner', 'parts'], $this->parser(['with' => '[owner,parts]'])->getWith());
        $this->assertSame(['owner', 'parts'], $this->parser(['with' => ['owner', 'parts']])->getWith());
        $this->assertSame(['owner'], $this->parser(['with' => 'owner'])->getWith());
        $this->assertSame([], $this->parser([])->getWith());
    }

    public function testSortsAreSplitIntoColumnAndDirection(): void
    {
        $this->assertSame(
            [['created_at', 'DESC']],
            $this->parser(['sort' => 'created_at_DESC'])->getSorts()
        );
    }

    public function testLimitAndPageFallBackToDefaults(): void
    {
        $parser = $this->parser([]);

        $this->assertSame(QueryParser::DEFAULT_LIMIT, $parser->getLimit());
        $this->assertSame(QueryParser::DEFAULT_PAGE, $parser->getPage());
    }

    public function testIsCountOnlyTrueForTheLiteralString(): void
    {
        $this->assertTrue($this->parser(['count' => 'true'])->isCount());
        $this->assertFalse($this->parser(['count' => '1'])->isCount());
        $this->assertFalse($this->parser([])->isCount());
    }

    public function testEmptyQueryValueDoesNotWarn(): void
    {
        $wheres = $this->withoutPhpErrors(fn () => $this->parser(['foo' => ''])->getWheres());

        $this->assertSame([['foo', '=', '']], $wheres);
    }

    public function testWhereWithNoOperatorDoesNotWarn(): void
    {
        $wheres = $this->withoutPhpErrors(fn () => $this->parser(['where' => '[bad]'])->getWheres());

        $this->assertSame([['bad']], $wheres);
    }

    public function testEmptyBracketedValueDoesNotWarn(): void
    {
        $wheres = $this->withoutPhpErrors(fn () => $this->parser(['where' => '[a=]'])->getWheres());

        $this->assertSame([['a', '=', '']], $wheres);
    }

    /**
     * PHPUnit's failOnWarning does not catch E_WARNING raised inside the code under
     * test, so the handler is installed per-call and the collected messages asserted.
     */
    private function withoutPhpErrors(callable $fn)
    {
        $errors = [];
        set_error_handler(function ($number, $message) use (&$errors) {
            $errors[] = $message;
            return true;
        });

        try {
            $result = $fn();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $errors);

        return $result;
    }

    public function testMissingParameterThrows(): void
    {
        $this->expectException(MissingParameterException::class);

        $this->parser([])->nope;
    }
}
