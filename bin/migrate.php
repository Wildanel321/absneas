<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Autoloader;
use App\Core\Env;

$basePath = dirname(__DIR__);
require_once $basePath . '/app/Core/Autoloader.php';
Autoloader::register($basePath);
Env::load($basePath . '/.env');

$config = require $basePath . '/config/app.php';
$app = new Application($config);
$db = $app->db();

$migrationsDir = (string) $config['migrations_path'];
$files = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
sort($files, SORT_STRING);

// Pastikan tabel migrations tersedia
$db->pdo()->exec("CREATE TABLE IF NOT EXISTS migrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT NOT NULL UNIQUE,
    executed_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
)");

$executed = [];
foreach ($db->fetchAll('SELECT filename FROM migrations') as $row) {
    $executed[(string) $row['filename']] = true;
}

$appliedCount = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($executed[$name])) {
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Gagal membaca file migrasi: {$name}\n");
        exit(1);
    }

    $db->transaction(static function () use ($db, $sql, $name): void {
        $db->pdo()->exec($sql);
        $db->execute('INSERT INTO migrations (filename) VALUES (:filename)', [':filename' => $name]);
    });

    $appliedCount++;
    fwrite(STDOUT, "[MIGRATE] Berhasil menjalankan {$name}\n");
}

// Buat akun admin awal jika belum ada user sama sekali
$userCount = (int) $db->fetchValue('SELECT COUNT(*) FROM users', [], 0);
if ($userCount === 0) {
    $initial = $config['initial_admin'];
    $deptId = $db->fetchValue("SELECT id FROM departments WHERE name = 'Umum' LIMIT 1");
    $db->execute(
        'INSERT INTO users (employee_code, full_name, username, email, password_hash, role, department_id, position, is_active)
         VALUES (:code, :name, :username, :email, :hash, :role, :dept, :pos, 1)',
        [
            ':code' => 'ADM-001',
            ':name' => (string) $initial['name'],
            ':username' => (string) $initial['username'],
            ':email' => '',
            ':hash' => password_hash((string) $initial['password'], PASSWORD_DEFAULT),
            ':role' => 'admin',
            ':dept' => $deptId ? (int) $deptId : null,
            ':pos' => 'Administrator Sistem',
        ]
    );

    fwrite(STDOUT, "[INIT] Akun admin awal dibuat: username='{$initial['username']}' (segera ganti password setelah login pertama).\n");
}

// Pastikan kiosk_display_key ada di settings
$kioskKey = $db->fetchValue("SELECT value FROM settings WHERE key = 'kiosk_display_key'");
if ($kioskKey === null || $kioskKey === '') {
    $envKiosk = (string) ($config['kiosk_key'] ?? '');
    if ($envKiosk === '' || str_starts_with($envKiosk, 'CHANGE_ME')) {
        $envKiosk = bin2hex(random_bytes(16));
    }
    $db->execute(
        "INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES ('kiosk_display_key', :val, datetime('now', 'localtime'))",
        [':val' => $envKiosk]
    );
    fwrite(STDOUT, "[INIT] Kiosk display key siap digunakan.\n");
}

if ($appliedCount === 0) {
    fwrite(STDOUT, "[MIGRATE] Semua migrasi sudah terbaru.\n");
} else {
    fwrite(STDOUT, "[MIGRATE] Selesai. {$appliedCount} migrasi diterapkan.\n");
}
