<?php

namespace Inertia;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class Middleware
{
    /**
     * Determines the current Inertia asset version hash.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request)
    {
        //
    }

    /**
     * Defines the Inertia props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request)
    {
        return [
            'errors' => function () use ($request) {
                return $this->resolveValidationErrors($request);
            },
        ];
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Closure  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next)
    {
        Inertia::share($this->share($request));

        Inertia::version(function () use ($request) {
            return $this->version($request);
        });

        $response = $next($request);
        $response = $this->checkVersion($request, $response);
        $response = $this->changeRedirectCode($request, $response);

        return $response;
    }

    /**
     * In the event that the asset version changes, Inertia will automatically reload
     * the page in order to ensure the application keeps working for the end user.
     *
     * @param  Request  $request
     * @param  Response  $response
     * @return Response
     */
    public function checkVersion(Request $request, Response $response)
    {
        if ($request->header('X-Inertia') &&
            $request->method() === 'GET' &&
            $request->header('X-Inertia-Version', '') !== Inertia::getVersion()
        ) {
            if ($request->hasSession()) {
                $request->session()->reflash();
            }

            return new Response('', 409, ['X-Inertia-Location' => $request->fullUrl()]);
        }

        return $response;
    }

    /**
     * Changes the status code during an Inertia redirect, so that the browser
     * makes the request as GET instead, avoiding unsupported method calls.
     *
     * @param  Request  $request
     * @param  Response  $response
     * @return Response
     */
    public function changeRedirectCode(Request $request, Response $response)
    {
        if ($response instanceof RedirectResponse &&
            $request->header('X-Inertia') &&
            $response->getStatusCode() === 302 &&
            in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])
        ) {
            $response->setStatusCode(303);
        }

        return $response;
    }

    /**
     * Resolves and prepares validation errors in such a way
     * that they are easier to use in the Inertia client.
     *
     * @param  Request  $request
     * @return object
     */
    public function resolveValidationErrors(Request $request)
    {
        if (! $request->session()->has('errors')) {
            return (object) [];
        }

        return (object) Collection::make($request->session()->get('errors')->getBags())->map(function ($bag) {
            return (object) Collection::make($bag->messages())->map(function ($errors) {
                return $errors[0];
            })->toArray();
        })->pipe(function ($bags) {
            return $bags->has('default') ? $bags->get('default') : $bags->toArray();
        });
    }
}
