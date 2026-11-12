<?php

namespace RBMowatt\BaseTests;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Routing\Controller;
use RBMowatt\Base\Controllers\Api\BaseApiController;
use RBMowatt\Base\Rest\Interfaces\ApiResponseInterface;
use RBMowatt\Base\Rest\Query\QueryParser;

class ReuseUser extends AuthUser
{
    protected $table = 'users';
}

class ReuseController extends BaseApiController
{
    public static $constructed = 0;

    protected $queryParser;

    public function __construct(ApiResponseInterface $response, QueryParser $queryParser)
    {
        parent::__construct($response);
        $this->queryParser = $queryParser;
        self::$constructed++;
    }

    public function show()
    {
        return response()->json([
            'if_none_match' => $this->request->header('If-None-Match'),
            'user' => $this->user ? $this->user->getAuthIdentifier() : null,
            'probe' => $this->queryParser->has('probe') ? $this->queryParser->probe : null,
            'href' => $this->response->href,
            'responseId' => $this->response->responseId,
        ]);
    }
}

class HostController extends Controller
{
    public static $constructed = 0;

    public function __construct()
    {
        self::$constructed++;
    }

    public function show()
    {
        return response()->json([]);
    }
}

class ControllerReuseTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        $app['config']->set('auth.providers.users.model', ReuseUser::class);
    }

    protected function defineRoutes($router)
    {
        $router->middleware([Authenticate::class])->get('/reuse', [ReuseController::class, 'show']);
        $router->get('/host', [HostController::class, 'show']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        ReuseController::$constructed = 0;
        HostController::$constructed = 0;
    }

    private function user($id): ReuseUser
    {
        $user = new ReuseUser();
        $user->setAttribute('id', $id);

        return $user;
    }

    private function twoRequests(): array
    {
        $first = $this->actingAs($this->user(1))->get('/reuse?probe=first', ['If-None-Match' => '"aaa"'])->json();
        $second = $this->actingAs($this->user(2))->get('/reuse?probe=second', ['If-None-Match' => '"bbb"'])->json();

        return [$first, $second];
    }

    public function testEachRequestGetsItsOwnController(): void
    {
        $this->twoRequests();

        $this->assertSame(2, ReuseController::$constructed);
    }

    public function testTheSecondRequestSeesItsOwnHeaders(): void
    {
        [$first, $second] = $this->twoRequests();

        $this->assertSame('"aaa"', $first['if_none_match']);
        $this->assertSame('"bbb"', $second['if_none_match']);
    }

    public function testTheSecondRequestSeesItsOwnUser(): void
    {
        [, $second] = $this->twoRequests();

        $this->assertSame(2, $second['user']);
    }

    public function testAnInjectedQueryParserReadsTheSecondRequest(): void
    {
        [, $second] = $this->twoRequests();

        $this->assertSame('second', $second['probe']);
    }

    public function testTheEnvelopeIsBuiltForTheSecondRequest(): void
    {
        [$first, $second] = $this->twoRequests();

        $this->assertSame('/reuse?probe=second', $second['href']);
        $this->assertNotSame($first['responseId'], $second['responseId']);
    }

    public function testHostAppControllersAreLeftAlone(): void
    {
        $this->get('/host')->assertOk();
        $this->get('/host')->assertOk();

        $this->assertSame(1, HostController::$constructed);
    }
}
