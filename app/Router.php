<?php

namespace App;

/**
 * Roteador minimalista: registra rotas (método + padrão) e despacha
 * para closures ou [Controller::class, 'metodo'].
 */
class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable|array $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable|array $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable|array $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    public function dispatch(string $method, string $requestUri): void
    {
        $method = strtoupper($method);
        $uri = rtrim((string) parse_url($requestUri, PHP_URL_PATH), '/');

        if ($uri === '') {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $regex = preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $route['pattern']);
            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches);
                $handler = $route['handler'];

                if (is_array($handler)) {
                    [$class, $methodName] = $handler;
                    (new $class())->$methodName(...$matches);
                } else {
                    $handler(...$matches);
                }

                return;
            }
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => "Rota não encontrada: {$method} {$uri}"]);
    }
}
