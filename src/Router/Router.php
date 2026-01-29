<?php

declare(strict_types=1);

namespace App\Router;

use Swoole\HTTP\Request;
use Swoole\HTTP\Response;

class Router
{
    private array $routes = [];
    private array $services = [];

    public function setServices(array $services): void
    {
        $this->services = $services;
    }

    public function get(string $path, callable|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable|array $handler): void
    {
        $pattern = $this->convertPathToRegex($path);
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    private function convertPathToRegex(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(Request $request, Response $response): void
    {
        $method = $request->server['request_method'];
        $uri = $request->server['request_uri'];

        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $request->get = array_merge($request->get ?? [], $params);

                $this->executeHandler($route['handler'], $request, $response);
                return;
            }
        }

        $response->status(404);
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode([
            'error' => 'Not Found',
            'message' => "Route {$method} {$uri} not found",
        ]));
    }

    private function executeHandler(callable|array $handler, Request $request, Response $response): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = $this->resolveController($class);
            $controller->$method($request, $response);
        } else {
            $handler($request, $response);
        }
    }

    private function resolveController(string $class): object
    {
        if (isset($this->services[$class])) {
            return $this->services[$class];
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if (isset($this->services[$typeName])) {
                    $params[] = $this->services[$typeName];
                }
            }
        }

        return $reflection->newInstanceArgs($params);
    }
}
