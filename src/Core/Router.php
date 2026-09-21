<?php

namespace App\Core;

class Router
{
    /** @var array<int, array{method: string, pattern: string, paramNames: array<int, string>, handler: callable, middleware: array<int, callable>}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $paramNames = [];
        $pattern = preg_replace_callback('#:([a-zA-Z_]+)#', function ($matches) use (&$paramNames) {
            $paramNames[] = $matches[1];
            return '([^/]+)';
        }, $path);

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => '#^' . $pattern . '$#',
            'paramNames' => $paramNames,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    public function dispatch(Request $request): void
    {
        $methodMatchedPaths = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $request->path, $matches)) {
                continue;
            }

            if ($route['method'] !== $request->method) {
                $methodMatchedPaths[] = $route['pattern'];
                continue;
            }

            array_shift($matches);
            $request->params = array_combine($route['paramNames'], $matches);

            foreach ($route['middleware'] as $middleware) {
                $middleware($request);
            }

            ($route['handler'])($request);
            return;
        }

        if (!empty($methodMatchedPaths)) {
            Response::error('Método no permitido', 405);
        }

        Response::error('Ruta no encontrada', 404);
    }
}
