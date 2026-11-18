<?php

declare(strict_types=1);

use App\Core\Autoloader;
use App\Core\Env;
use App\Services\BackupService;

$basePath = dirname(__DIR__);
require_once $basePath . '/app/Core/Autoloader.php';
Autoloader::register($basePath);
Env::load($basePath . '/.env');

$config = require $basePath . '/config/app.php';
date_default_timezone_set((string) ($config['timezone'] ?? 'Asia/Jakarta'));

$prefix = $argv[1] ?? 'daily';
$service = new BackupService(
    (string) $config['db_path'],
    (string) $config['backup_local_dir'],
    (string) $config['backup_usb_dir'],
    (int) ($config['backup_retention_days'] ?? 30)
);

try {
    $result = $service->createBackup($prefix);
    fwrite(STDOUT, sprintf(
        "[BACKUP] %s | size=%d bytes | usb_synced=%s | pruned_local=%d | pruned_usb=%d\n",
        $result['message'],
        $result['size_bytes'],
        $result['usb_synced'] ? 'yes' : 'no',
        $result['pruned_local'],
        $result['pruned_usb']
    ));
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "[BACKUP ERROR] " . $e->getMessage() . "\n");
    exit(1);
}
