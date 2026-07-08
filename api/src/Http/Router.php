<?php

namespace App\Http;

/**
 * Router mínimo basic-path con soporte de parámetros {id} y middlewares.
 *
 * Uso:
 *   $router->get('/api/health', [HealthController::class, 'index']);
 *   $router->get('/api/practicas/{id}', [PracticaController::class, 'show']);
 *   $router->get('/api/auth/me', [AuthController::class, 'me'], [ [AuthMiddleware::class, 'handle'] ]);
 *
 * Cada middleware es un callable o un par [Clase::class, 'metodo']; recibe los
 * $params de la ruta y debe lanzar App\Http\HttpException para bloquear. Las
 * excepciones no se capturan aquí: las maneja el front controller (index.php).
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable|array, middlewares:array}> */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler, array $middlewares = []): void
    {
        $this->add('GET', $pattern, $handler, $middlewares);
    }

    public function post(string $pattern, callable|array $handler, array $middlewares = []): void
    {
        $this->add('POST', $pattern, $handler, $middlewares);
    }

    public function put(string $pattern, callable|array $handler, array $middlewares = []): void
    {
        $this->add('PUT', $pattern, $handler, $middlewares);
    }

    public function patch(string $pattern, callable|array $handler, array $middlewares = []): void
    {
        $this->add('PATCH', $pattern, $handler, $middlewares);
    }

    public function delete(string $pattern, callable|array $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middlewares);
    }

    private function add(string $method, string $pattern, callable|array $handler, array $middlewares = []): void
    {
        $this->routes[] = [
            'method'      => $method,
            'pattern'     => $pattern,
            'handler'     => $handler,
            'middlewares' => $middlewares,
        ];
    }

    /**
     * Resuelve el método y la ruta actuales, ejecuta los middlewares y luego el handler.
     */
    public function dispatch(string $method, string $path): void
    {
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $regex = $this->toRegex($route['pattern']);
            if (preg_match($regex, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Los middlewares corren antes del handler; si bloquean, lanzan HttpException.
                foreach ($route['middlewares'] as $middleware) {
                    $this->invoke($middleware, $params);
                }

                $this->invoke($route['handler'], $params);
                return;
            }
        }

        Response::error('not_found', 'Recurso no encontrado.', 404);
    }

    private function toRegex(string $pattern): string
    {
        $pattern = rtrim($pattern, '/') ?: '/';
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    /**
     * Invoca un callable o un par [Clase::class, 'metodo'] (instanciando la clase),
     * pasándole los parámetros de la ruta. Sirve tanto para middlewares como handlers.
     */
    private function invoke(callable|array $handler, array $params): void
    {
        // Un par [Clase::class, 'metodo'] siempre se instancia (método de instancia).
        // Los closures (incluidos los que devuelve RoleMiddleware) se invocan directo.
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = new $class();
            $instance->$method($params);
            return;
        }
        $handler($params);
    }
}
