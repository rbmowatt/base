<?php

namespace RBMowatt\BaseTests;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Auth\User as AuthUser;
use RBMowatt\Base\Controllers\Api\BaseApiController;
use RBMowatt\Base\Rest\Interfaces\ApiResponseInterface;

class TimingUser extends AuthUser
{
    protected $table = 'users';
}

class TimingController extends BaseApiController
{
    public static $userSeenInConstructor = 'not-run';

    public static $uriSeenInConstructor = null;

    public function __construct(ApiResponseInterface $response)
    {
        parent::__construct($response);

        self::$userSeenInConstructor = $this->user ? $this->user->getAuthIdentifier() : null;
        self::$uriSeenInConstructor = $this->request->getRequestUri();
    }

    public function show()
    {
        return $this->response->ok([
            'in_constructor' => self::$userSeenInConstructor,
            'in_action' => auth()->id(),
        ]);
    }
}

class ControllerAuthTimingTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', TimingUser::class);
    }

    protected function defineRoutes($router)
    {
        $router->middleware([Authenticate::class])->get('/timing', [TimingController::class, 'show']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        TimingController::$userSeenInConstructor = 'not-run';
        TimingController::$uriSeenInConstructor = null;
    }

    private function user($id): TimingUser
    {
        $user = new TimingUser();
        $user->setAttribute('id', $id);

        return $user;
    }

    public function testTheUserIsAlreadyResolvedWhenTheConstructorRuns(): void
    {
        // route middleware runs before the controller is instantiated, so the guard
        // is populated by the time parent::__construct() asks for it
        $response = $this->actingAs($this->user(42))->get('/timing');

        $response->assertOk();
        $this->assertSame(42, TimingController::$userSeenInConstructor);
        $this->assertSame(42, $response->json('data.in_action'));
    }

    public function testTheConstructorSeesTheRequestBeingHandled(): void
    {
        $this->actingAs($this->user(1))->get('/timing?probe=constructor')->assertOk();

        $this->assertSame('/timing?probe=constructor', TimingController::$uriSeenInConstructor);
    }

    public function testUserIsNullWhenNoAuthIsBound(): void
    {
        $this->app->offsetUnset('auth');

        new TimingController($this->app->make(ApiResponseInterface::class));

        $this->assertNull(TimingController::$userSeenInConstructor);
    }
}
