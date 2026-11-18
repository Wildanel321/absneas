<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLogModel;
use App\Models\SettingModel;
use App\Services\AuthService;

final class AuthController
{
    public function __construct(
        private Application $app,
        private AuthService $auth,
        private SettingModel $settings,
        private AuditLogModel $audit
    ) {
    }

    public function home(Request $req): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/login');
        }

        return Response::redirect($this->auth->isAdmin() ? '/admin' : '/app');
    }

    public function showLogin(Request $req): Response
    {
        if ($this->auth->check()) {
            return Response::redirect($this->auth->isAdmin() ? '/admin' : '/app');
        }

        $html = $this->app->view()->render('auth/login', [
            'title' => 'Masuk - ' . $this->app->config('name'),
            'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
            'oldUsername' => '',
            'error' => null,
        ]);

        return Response::html($html);
    }

    public function login(Request $req): Response
    {
        $username = Validator::str($req->input('username', ''), 40);
        $password = (string) $req->input('password', '');

        if ($username === '' || $password === '') {
            if ($req->expectsJson()) {
                return Response::json(['ok' => false, 'message' => 'Username dan password wajib diisi.'], 422);
            }
            $html = $this->app->view()->render('auth/login', [
                'title' => 'Masuk - ' . $this->app->config('name'),
                'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
                'oldUsername' => $username,
                'error' => 'Username dan password wajib diisi.',
            ]);
            return Response::html($html, 422);
        }

        $result = $this->auth->attempt($username, $password, $req->ip());
        if (!$result['ok']) {
            if ($req->expectsJson()) {
                return Response::json(['ok' => false, 'message' => $result['message']], (int) $result['status']);
            }
            $html = $this->app->view()->render('auth/login', [
                'title' => 'Masuk - ' . $this->app->config('name'),
                'officeName' => $this->settings->get('office_name', 'Kantor Pusat'),
                'oldUsername' => $username,
                'error' => $result['message'],
            ]);
            return Response::html($html, (int) $result['status']);
        }

        $user = $result['user'];
        if (($user['role'] ?? '') === 'admin') {
            $this->audit->record(
                (int) $user['id'],
                'admin.login',
                'user',
                (string) $user['id'],
                ['username' => $user['username']],
                $req->ip(),
                $req->userAgent()
            );
        }

        $target = ($user['role'] ?? '') === 'admin' ? '/admin' : '/app';
        if ($req->expectsJson()) {
            return Response::json(['ok' => true, 'redirect' => $target, 'message' => 'Login berhasil.']);
        }

        return Response::redirect($target);
    }

    public function logout(Request $req): Response
    {
        $user = $this->auth->user();
        if ($user !== null && ($user['role'] ?? '') === 'admin') {
            $this->audit->record(
                (int) $user['id'],
                'admin.logout',
                'user',
                (string) $user['id'],
                ['username' => $user['username']],
                $req->ip(),
                $req->userAgent()
            );
        }

        $this->auth->logout();
        Session::start(
            (int) $this->app->config('session_lifetime_minutes', 480),
            $req->isHttps()
        );
        Session::flash('success', 'Anda telah keluar dari sesi.');

        return Response::redirect('/login');
    }
}
