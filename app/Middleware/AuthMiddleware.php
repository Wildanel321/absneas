<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Models\SettingModel;
use App\Services\AuthService;

final class AuthMiddleware
{
    public function __construct(
        private AuthService $auth,
        private SettingModel $settings
    ) {
    }

    public function requireAuth(): callable
    {
        return function (Request $req, callable $next): Response {
            if (!$this->auth->check()) {
                if ($req->expectsJson()) {
                    return Response::json(['ok' => false, 'message' => 'Sesi berakhir. Silakan login kembali.'], 401);
                }
                return Response::redirect('/login');
            }
            return $next($req);
        };
    }

    public function requireAdmin(): callable
    {
        return function (Request $req, callable $next): Response {
            if (!$this->auth->check()) {
                if ($req->expectsJson()) {
                    return Response::json(['ok' => false, 'message' => 'Autentikasi diperlukan.'], 401);
                }
                return Response::redirect('/login');
            }
            if (!$this->auth->isAdmin()) {
                if ($req->expectsJson()) {
                    return Response::json(['ok' => false, 'message' => 'Akses hanya untuk administrator.'], 403);
                }
                return Response::redirect('/app');
            }
            return $next($req);
        };
    }

    public function verifyCsrf(): callable
    {
        return static function (Request $req, callable $next): Response {
            if (!Csrf::verify($req->csrfToken())) {
                if ($req->expectsJson()) {
                    return Response::json(['ok' => false, 'message' => 'Token keamanan (CSRF) tidak valid. Muat ulang halaman lalu coba lagi.'], 419);
                }
                return Response::html('Token keamanan (CSRF) tidak valid. Muat ulang halaman lalu coba lagi.', 419);
            }
            return $next($req);
        };
    }

    public function requireKioskAccess(): callable
    {
        return function (Request $req, callable $next): Response {
            if ($this->auth->isAdmin()) {
                return $next($req);
            }

            $storedKey = $this->settings->get('kiosk_display_key', '');
            $providedKey = (string) ($req->query('key') ?? $req->header('X-Kiosk-Key') ?? '');

            if ($storedKey !== '' && $providedKey !== '' && hash_equals($storedKey, $providedKey)) {
                return $next($req);
            }

            if ($req->expectsJson()) {
                return Response::json(['ok' => false, 'message' => 'Akses monitor QR memerlukan login administrator atau kunci kiosk yang valid.'], 403);
            }
            return Response::redirect('/login');
        };
    }

    public function requireAuthOrKioskAccess(): callable
    {
        return function (Request $req, callable $next): Response {
            if ($this->auth->check()) {
                return $next($req);
            }

            $storedKey = $this->settings->get('kiosk_display_key', '');
            $providedKey = (string) ($req->query('key') ?? $req->header('X-Kiosk-Key') ?? '');

            if ($storedKey !== '' && $providedKey !== '' && hash_equals($storedKey, $providedKey)) {
                return $next($req);
            }

            if ($req->expectsJson()) {
                return Response::json(['ok' => false, 'message' => 'Akses memerlukan login atau kunci kiosk yang valid.'], 403);
            }
            return Response::redirect('/login');
        };
    }
}
