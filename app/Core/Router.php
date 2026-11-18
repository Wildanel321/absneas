<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /**
     * @var list<array{method: string, pattern: string, regex: string, params: list<string>, handler: callable, middleware: list<callable>}>
     */
    private array $routes = [];

    /**
     * @param list<callable> $middleware
     */
    public function add(string $method, string $pattern, callable $handler, array $middleware = []): void
    {
        $normalized = '/' . trim($pattern, '/');
        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        $params = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $m) use (&$params): string {
            $params[] = $m[1];
            return '([^/]+)';
        }, $normalized);

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $normalized,
            'regex' => '#^' . $regex . '$#',
            'params' => $params,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * @param list<callable> $middleware
     */
    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /**
     * @param list<callable> $middleware
     */
    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            array_shift($matches);
            $routeParams = [];
            foreach ($route['params'] as $idx => $name) {
                $routeParams[$name] = isset($matches[$idx]) ? urldecode($matches[$idx]) : '';
            }

            $handler = $route['handler'];
            $next = static function (Request $req) use ($handler, $routeParams): Response {
                return $handler($req, $routeParams);
            };

            foreach (array_reverse($route['middleware']) as $mw) {
                $currentNext = $next;
                $next = static function (Request $req) use ($mw, $currentNext): Response {
                    return $mw($req, $currentNext);
                };
            }

            return $next($request);
        }

        if ($request->expectsJson()) {
            return Response::json(['ok' => false, 'message' => 'Endpoint tidak ditemukan.'], 404);
        }

        return Response::html(
            '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>404 Tidak Ditemukan</title><link rel="stylesheet" href="/assets/css/app.css"></head><body class="auth-body"><main class="auth-shell"><section class="card auth-card"><h1>Halaman tidak ditemukan</h1><p class="muted">Alamat yang Anda buka tidak tersedia di aplikasi ini.</p><a class="btn btn-primary" href="/">Kembali ke Beranda</a></section></main></body></html>',
            404
        );
    }
}
