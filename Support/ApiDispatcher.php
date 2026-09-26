<?php

namespace App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Support;

use App\Vito\Plugins\Pietervanleuven\VitodeployMcp\Mcp\ToolError;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Calls one of Vito's named REST API routes in-process, as the caller.
 *
 * The sub-request runs the route's full middleware stack (auth:sanctum,
 * ability:read/write, can-see-project) and its controller's validation and
 * policies, so a tool can never do more than the caller's token could do over
 * HTTP, and the plugin re-implements none of it.
 */
final class ApiDispatcher
{
    public function __construct(
        private readonly Request $parent,
        private readonly Router $router,
        private readonly ExceptionHandler $exceptions,
    ) {}

    /**
     * Route parameters are taken from `<snake_param>_id` arguments (`cronJob`
     * from `cron_job_id`); every other argument is sent as the query string
     * for GET routes and as the JSON body otherwise.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @throws ToolError when the API answers with an error status
     */
    public function call(string $routeName, array $arguments = []): mixed
    {
        $route = $this->router->getRoutes()->getByName($routeName)
            ?? throw new ToolError("Vito API route {$routeName} does not exist in this Vito version.");
        $method = $route->methods()[0];

        $parameters = [];
        foreach ($route->parameterNames() as $name) {
            $argument = Str::snake($name).'_id';
            if (array_key_exists($argument, $arguments)) {
                $parameters[$name] = $arguments[$argument];
                unset($arguments[$argument]);
            }
        }

        $path = route($routeName, $parameters, absolute: false);
        $request = $this->makeRequest($method, $path, $arguments);

        return $this->decode($method, $path, $this->dispatch($request));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function makeRequest(string $method, string $path, array $arguments): Request
    {
        $hasBody = $method !== 'GET' && $arguments !== [];

        $request = Request::create(
            $path,
            $method,
            $method === 'GET' ? $arguments : [],
            server: array_filter([
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => $hasBody ? 'application/json' : null,
                'HTTP_AUTHORIZATION' => $this->parent->headers->get('Authorization'),
                'REMOTE_ADDR' => $this->parent->server('REMOTE_ADDR'),
            ]),
            content: $hasBody ? json_encode($arguments, JSON_THROW_ON_ERROR) : null,
        );
        $request->setUserResolver($this->parent->getUserResolver());

        return $request;
    }

    private function dispatch(Request $request): Response
    {
        $container = app();
        $previous = $container->make('request');
        $previousRoute = $container->bound(Route::class) ? $container->make(Route::class) : null;

        // Guards and the user resolver follow the container's request, so swap
        // it for the duration of the sub-request and always put it back.
        $container->instance('request', $request);
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $e) {
            // Exceptions thrown outside the route pipeline are rendered the
            // same way the HTTP kernel would render them.
            $this->exceptions->report($e);

            return $this->exceptions->render($request, $e);
        } finally {
            $container->instance('request', $previous);
            if ($previousRoute !== null) {
                $container->instance(Route::class, $previousRoute);
            }
        }
    }

    private function decode(string $method, string $path, Response $response): mixed
    {
        $status = $response->getStatusCode();
        $content = (string) $response->getContent();

        if ($content === '') {
            if ($status >= 400) {
                throw new ToolError("Vito API {$method} {$path} failed with {$status}.");
            }

            return ['success' => true, 'status' => $status];
        }

        $data = json_decode($content, true);
        $isJson = json_last_error() === JSON_ERROR_NONE;

        if ($status >= 400) {
            throw new ToolError(sprintf(
                'Vito API %s %s failed with %d: %s',
                $method,
                $path,
                $status,
                $isJson ? self::errorMessage($data) : mb_strimwidth($content, 0, 500, '… [truncated]'),
            ));
        }

        return $isJson ? $data : $content;
    }

    private static function errorMessage(mixed $data): string
    {
        if (! is_array($data)) {
            return json_encode($data, JSON_UNESCAPED_SLASHES) ?: 'Unknown error';
        }

        $message = is_string($data['message'] ?? null) ? $data['message'] : 'Unknown error';
        if (is_array($data['errors'] ?? null)) {
            $message .= ' '.json_encode($data['errors'], JSON_UNESCAPED_SLASHES);
        }

        return $message;
    }
}
