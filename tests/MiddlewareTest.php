<?php

namespace Inertia\Tests;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Inertia\Inertia;
use Inertia\Tests\middleware\ExampleMiddleware;

class MiddlewareTest extends TestCase
{
    public function test_the_version_is_optional()
    {
        $this->prepareMockEndpoint();

        $response = $this->get('/', [
            'X-Inertia' => 'true',
        ]);

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_the_version_can_be_a_number()
    {
        $this->prepareMockEndpoint($version = 1597347897973);

        $response = $this->get('/', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ]);

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_the_version_can_be_a_string()
    {
        $this->prepareMockEndpoint($version = 'foo-version');

        $response = $this->get('/', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ]);

        $response->assertSuccessful();
        $response->assertJson(['component' => 'User/Edit']);
    }

    public function test_it_will_instruct_inertia_to_reload_on_a_version_mismatch()
    {
        $this->prepareMockEndpoint('1234');

        $response = $this->get('/', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '4321',
        ]);

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', $this->baseUrl);
        self::assertEmpty($response->getContent());
    }

    public function test_validation_errors_are_registered_as_of_default()
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            $this->assertInstanceOf(\Closure::class, Inertia::getShared('errors'));
        });

        $this->withoutExceptionHandling()->get('/');
    }

    public function test_validation_errors_can_be_empty()
    {
        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            $errors = Inertia::getShared('errors')();

            $this->assertIsObject($errors);
            $this->assertEmpty(get_object_vars($errors));
        });

        $this->withoutExceptionHandling()->get('/');
    }

    public function test_validation_errors_are_returned_in_the_correct_format()
    {
        Session::put('errors', (new ViewErrorBag())->put('default', new MessageBag([
            'name' => 'The name field is required.',
            'email' => 'Not a valid email address.',
        ])));

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            $errors = Inertia::getShared('errors')();

            $this->assertIsObject($errors);
            $this->assertSame('The name field is required.', $errors->name);
            $this->assertSame('Not a valid email address.', $errors->email);
        });

        $this->withoutExceptionHandling()->get('/');
    }

    public function test_validation_errors_with_named_error_bags_are_scoped()
    {
        Session::put('errors', (new ViewErrorBag())->put('example', new MessageBag([
            'name' => 'The name field is required.',
            'email' => 'Not a valid email address.',
        ])));

        Route::middleware([StartSession::class, ExampleMiddleware::class])->get('/', function () {
            $errors = Inertia::getShared('errors')();

            $this->assertIsObject($errors);
            $this->assertSame('The name field is required.', $errors->example->name);
            $this->assertSame('Not a valid email address.', $errors->example->email);
        });

        $this->withoutExceptionHandling()->get('/');
    }

    public function test_default_validation_errors_can_be_overwritten()
    {
        Session::put('errors', (new ViewErrorBag())->put('example', new MessageBag([
            'name' => 'The name field is required.',
            'email' => 'Not a valid email address.',
        ])));

        $this->prepareMockEndpoint(null, ['errors' => 'foo']);
        $response = $this->get('/', ['X-Inertia' => 'true']);

        $response->assertJson([
            'props' => [
                'errors' => 'foo',
            ],
        ]);
    }

    private function prepareMockEndpoint($version = null, $shared = [])
    {
        return Route::middleware(StartSession::class)->get('/', function (Request $request) use ($version, $shared) {
            return (new ExampleMiddleware($version, $shared))->handle($request, function ($request) {
                return Inertia::render('User/Edit', ['user' => ['name' => 'Jonathan']])->toResponse($request);
            });
        });
    }
}
