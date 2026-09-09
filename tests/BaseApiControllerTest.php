<?php

namespace RBMowatt\BaseTests;

use Illuminate\Foundation\Auth\User;
use RBMowatt\Base\Controllers\Api\BaseApiController;
use RBMowatt\Base\Rest\ApiResponse;

class BaseApiControllerTest extends TestCase
{
    public function testConstructsAgainstTheStockAuthConfig(): void
    {
        $this->assertSame(['web'], array_keys(config('auth.guards')));

        $controller = new BaseApiController(new ApiResponse());

        $this->assertNull($this->userOf($controller));
    }

    public function testResolvesTheAuthenticatedUser(): void
    {
        $user = new User();
        $user->id = 42;
        $this->actingAs($user);

        $this->assertSame($user, $this->userOf(new BaseApiController(new ApiResponse())));
    }

    public function testASubclassCanPinAGuard(): void
    {
        config(['auth.guards.api' => ['driver' => 'session', 'provider' => 'users']]);

        $controller = new class (new ApiResponse()) extends BaseApiController {
            protected $guard = 'api';
        };

        $this->assertNull($this->userOf($controller));
    }

    public function testNoAuthBindingResolvesToNull(): void
    {
        $this->app->offsetUnset('auth');

        $this->assertNull($this->userOf(new BaseApiController(new ApiResponse())));
    }

    private function userOf(BaseApiController $controller)
    {
        $property = new \ReflectionProperty(BaseApiController::class, 'user');
        $property->setAccessible(true);

        return $property->getValue($controller);
    }
}
