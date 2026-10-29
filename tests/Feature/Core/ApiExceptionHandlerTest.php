<?php

namespace Tests\Feature\Core;

use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ApiExceptionHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/test-exception-auth', function () {
            throw new AuthenticationException();
        });

        Route::get('/api/test-exception-authorization', function () {
            throw new AuthorizationException('You are not allowed to access this resource.');
        });

        Route::get('/api/test-exception-access-denied', function () {
            throw new AccessDeniedHttpException('Access denied by policy.');
        });

        Route::get('/api/test-exception-validation', function () {
            throw ValidationException::withMessages([
                'email' => ['The email field is required.'],
            ]);
        });

        Route::get('/api/test-exception-business-default', function () {
            throw new BusinessException('Insufficient account balance.');
        });

        Route::get('/api/test-exception-business-custom', function () {
            throw new BusinessException('Fiscal year is locked.', 'FISCAL_YEAR_LOCKED');
        });

        Route::get('/api/test-exception-not-found', function () {
            throw new NotFoundException('Supplier invoice not found.');
        });

        Route::get('/api/test-exception-model-not-found', function () {
            $e = new ModelNotFoundException();
            $e->setModel('App\\Modules\\Purchase\\Models\\Purchase', [999]);
            throw $e;
        });

        Route::get('/api/test-exception-http-not-found', function () {
            throw new NotFoundHttpException('Endpoint does not exist.');
        });

        Route::get('/api/test-exception-conflict', function () {
            abort(409, 'Conflict with current entity state.');
        });

        Route::get('/api/test-exception-too-many-requests', function () {
            abort(429, 'Rate limit exceeded. Try again in 60 seconds.');
        });

        Route::get('/api/test-exception-server-error', function () {
            throw new \RuntimeException('Database connection lost unexpectedly.');
        });
    }

    public function test_authentication_exception_returns_401(): void
    {
        $response = $this->getJson('/api/test-exception-auth');

        $response->assertStatus(401)
            ->assertExactJson([
                'success' => false,
                'code'    => 'UNAUTHENTICATED',
                'message' => 'Unauthenticated.',
                'errors'  => null,
            ]);
    }

    public function test_authorization_exception_returns_403(): void
    {
        $response = $this->getJson('/api/test-exception-authorization');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code'    => 'FORBIDDEN',
                'message' => 'You are not allowed to access this resource.',
                'errors'  => null,
            ]);
    }

    public function test_access_denied_http_exception_returns_403(): void
    {
        $response = $this->getJson('/api/test-exception-access-denied');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code'    => 'FORBIDDEN',
                'message' => 'Access denied by policy.',
                'errors'  => null,
            ]);
    }

    public function test_validation_exception_returns_422(): void
    {
        $response = $this->getJson('/api/test-exception-validation');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('errors.email.0', 'The email field is required.');
    }

    public function test_business_exception_returns_422_with_default_code(): void
    {
        $response = $this->getJson('/api/test-exception-business-default');

        $response->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'code'    => 'BUSINESS_ERROR',
                'message' => 'Insufficient account balance.',
                'errors'  => null,
            ]);
    }

    public function test_business_exception_returns_422_with_custom_code(): void
    {
        $response = $this->getJson('/api/test-exception-business-custom');

        $response->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'code'    => 'FISCAL_YEAR_LOCKED',
                'message' => 'Fiscal year is locked.',
                'errors'  => null,
            ]);
    }

    public function test_not_found_exception_returns_404(): void
    {
        $response = $this->getJson('/api/test-exception-not-found');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'code'    => 'RESOURCE_NOT_FOUND',
                'message' => 'Supplier invoice not found.',
                'errors'  => null,
            ]);
    }

    public function test_model_not_found_exception_returns_404(): void
    {
        $response = $this->getJson('/api/test-exception-model-not-found');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code'    => 'RESOURCE_NOT_FOUND',
                'message' => 'Resource not found.',
                'errors'  => null,
            ]);
    }

    public function test_not_found_http_exception_returns_404(): void
    {
        $response = $this->getJson('/api/test-exception-http-not-found');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'code'    => 'RESOURCE_NOT_FOUND',
                'message' => 'Resource not found.',
                'errors'  => null,
            ]);
    }

    public function test_conflict_http_exception_returns_409(): void
    {
        $response = $this->getJson('/api/test-exception-conflict');

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code'    => 'CONFLICT',
                'message' => 'Conflict with current entity state.',
                'errors'  => null,
            ]);
    }

    public function test_rate_limit_http_exception_returns_429(): void
    {
        $response = $this->getJson('/api/test-exception-too-many-requests');

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'code'    => 'TOO_MANY_REQUESTS',
                'message' => 'Rate limit exceeded. Try again in 60 seconds.',
                'errors'  => null,
            ]);
    }

    public function test_unhandled_server_exception_returns_500(): void
    {
        $response = $this->getJson('/api/test-exception-server-error');

        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'INTERNAL_SERVER_ERROR');
    }
}

