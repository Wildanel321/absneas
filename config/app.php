<?php

declare(strict_types=1);

use App\Core\Env;

$basePath = dirname(__DIR__);
$dbPath = Env::get('DB_PATH', 'storage/database/attendance.sqlite');
if (!preg_match('/^([a-zA-Z]:[\\\\\\/]|\\/)/', $dbPath)) {
    $dbPath = $basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dbPath);
}

$backupLocal = Env::get('BACKUP_LOCAL_DIR', 'storage/backups');
if (!preg_match('/^([a-zA-Z]:[\\\\\\/]|\\/)/', $backupLocal)) {
    $backupLocal = $basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $backupLocal);
}

return [
    'name' => Env::get('APP_NAME', 'Smart Attendance'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Jakarta'),
    'key' => Env::get('APP_KEY', ''),
    'base_path' => $basePath,
    'db_path' => $dbPath,
    'storage_path' => $basePath . DIRECTORY_SEPARATOR . 'storage',
    'logs_path' => $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs',
    'views_path' => $basePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
    'migrations_path' => $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
    'session_lifetime_minutes' => Env::int('SESSION_LIFETIME_MINUTES', 480),
    'login_max_attempts' => Env::int('LOGIN_MAX_ATTEMPTS', 5),
    'login_window_minutes' => Env::int('LOGIN_WINDOW_MINUTES', 15),
    'trust_proxy_headers' => Env::bool('TRUST_PROXY_HEADERS', true),
    'kiosk_key' => Env::get('KIOSK_KEY', ''),
    'backup_local_dir' => $backupLocal,
    'backup_usb_dir' => Env::get('BACKUP_USB_DIR', '/mnt/usb-backup/smart-attendance'),
    'backup_retention_days' => Env::int('BACKUP_RETENTION_DAYS', 30),
    'initial_admin' => [
        'username' => Env::get('INITIAL_ADMIN_USERNAME', 'admin'),
        'password' => Env::get('INITIAL_ADMIN_PASSWORD', 'ChangeMe123!'),
        'name' => Env::get('INITIAL_ADMIN_NAME', 'Administrator'),
    ],
];
