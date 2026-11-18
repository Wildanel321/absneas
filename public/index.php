<?php

declare(strict_types=1);

use App\Core\Autoloader;
use App\Core\Bootstrap;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

// Saat dijalankan lewat PHP built-in server (pengujian lokal), layani file statis langsung
if (PHP_SAPI === 'cli-server') {
    $urlPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $staticFile = __DIR__ . $urlPath;
    if ($urlPath !== '/' && is_file($staticFile)) {
        return false;
    }
}

$basePath = dirname(__DIR__);
require_once $basePath . '/app/Core/Autoloader.php';
Autoloader::register($basePath);
Env::load($basePath . '/.env');

$config = require $basePath . '/config/app.php';
$request = Request::capture((bool) ($config['trust_proxy_headers'] ?? true));

Session::start(
    (int) ($config['session_lifetime_minutes'] ?? 480),
    $request->isHttps()
);

try {
    ['app' => $app, 'router' => $router] = Bootstrap::create($config);
    $response = $router->dispatch($request);
    $response->send();
} catch (\Throwable $e) {
    $debug = (bool) ($config['debug'] ?? false);
    if (isset($app)) {
        $app->logger()->error('Unhandled exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    if ($request->expectsJson()) {
        Response::json([
            'ok' => false,
            'message' => $debug ? $e->getMessage() : 'Terjadi kesalahan internal pada server.',
        ], 500)->send();
        exit;
    }

    $msg = $debug
        ? htmlspecialchars($e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')', ENT_QUOTES, 'UTF-8')
        : 'Terjadi kesalahan internal pada server. Silakan coba lagi beberapa saat.';
    Response::html(
        '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kesalahan Server</title><link rel="stylesheet" href="/assets/css/app.css"></head><body class="auth-body"><main class="auth-shell"><section class="card auth-card"><h1>Kesalahan Server</h1><p class="muted">' . $msg . '</p><a class="btn btn-primary" href="/">Kembali</a></section></main></body></html>',
        500
    )->send();
}
