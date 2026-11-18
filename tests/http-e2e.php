<?php

declare(strict_types=1);

/**
 * End-to-end HTTP integration test against a live Smart Attendance server.
 * Usage: php tests/http-e2e.php http://127.0.0.1:8080
 */

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$cookieFile = tempnam(sys_get_temp_dir(), 'sat_cookie_');
$passed = 0;
$failed = 0;

function check(bool $ok, string $label): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        fwrite(STDOUT, "  [PASS] {$label}\n");
    } else {
        $failed++;
        fwrite(STDERR, "  [FAIL] {$label}\n");
    }
}

/**
 * @param array<string, mixed>|null $postFields
 * @param array<int, string> $extraHeaders
 * @return array{status: int, body: string, headers: string}
 */
function httpReq(string $method, string $url, string $cookieFile, ?array $postFields = null, array $extraHeaders = [], ?string $jsonBody = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 10,
    ]);
    if ($jsonBody !== null) {
        $extraHeaders[] = 'Content-Type: application/json';
        $extraHeaders[] = 'Accept: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    } elseif ($postFields !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    }
    if ($extraHeaders !== []) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $extraHeaders);
    }

    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return [
        'status' => $status,
        'headers' => substr($raw, 0, $headerSize),
        'body' => substr($raw, $headerSize),
    ];
}

function extractCsrf(string $html): string
{
    if (preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

try {
    fwrite(STDOUT, "\n=== LIVE HTTP E2E VERIFICATION ({$base}) ===\n");

    // 1. Static PWA assets & health
    $h = httpReq('GET', $base . '/health', $cookieFile);
    check($h['status'] === 200 && str_contains($h['body'], '"ok":true'), 'GET /health -> 200 OK');

    $m = httpReq('GET', $base . '/manifest.json', $cookieFile);
    check($m['status'] === 200 && str_contains($m['body'], '"display": "standalone"'), 'GET /manifest.json -> 200 valid PWA manifest');

    $sw = httpReq('GET', $base . '/sw.js', $cookieFile);
    check($sw['status'] === 200 && str_contains($sw['body'], 'smart-attendance-static'), 'GET /sw.js -> 200 Service Worker');

    $css = httpReq('GET', $base . '/assets/css/app.css', $cookieFile);
    check($css['status'] === 200 && strlen($css['body']) > 5000, 'GET /assets/css/app.css -> 200 stylesheet');

    // 2. Login page & invalid login & valid admin login
    $loginPage = httpReq('GET', $base . '/login', $cookieFile);
    $csrf = extractCsrf($loginPage['body']);
    check($loginPage['status'] === 200 && $csrf !== '', 'GET /login -> 200 with CSRF token');
    check(str_contains($loginPage['headers'], 'Content-Security-Policy:'), 'Security header CSP present');
    check(str_contains($loginPage['headers'], 'X-Frame-Options: SAMEORIGIN'), 'Security header X-Frame-Options present');

    $badLogin = httpReq('POST', $base . '/login', $cookieFile, [
        '_csrf' => $csrf,
        'username' => 'admin',
        'password' => 'WrongPass!',
    ]);
    check($badLogin['status'] === 401 && str_contains($badLogin['body'], 'Username atau password salah'), 'POST /login salah -> 401 dengan pesan error');

    $goodLogin = httpReq('POST', $base . '/login', $cookieFile, [
        '_csrf' => $csrf,
        'username' => 'admin',
        'password' => 'ChangeMe123!',
    ]);
    check($goodLogin['status'] === 302 && str_contains($goodLogin['headers'], 'Location: /admin'), 'POST /login admin valid -> 302 ke /admin');

    // 3. All Admin pages render 200 with expected content
    foreach ([
        '/admin' => 'Pemantauan Kehadiran',
        '/admin/employees' => 'Manajemen Karyawan',
        '/admin/leaves' => 'Pengajuan Izin',
        '/admin/reports' => 'Laporan Kehadiran',
        '/admin/settings' => 'Aturan Absensi',
        '/admin/backups' => 'Backup &amp; Restore Database SQLite',
        '/admin/audit-logs' => 'Log Audit Aktivitas Administrator',
        '/kiosk' => 'kioskQrBox',
    ] as $path => $needle) {
        $res = httpReq('GET', $base . $path, $cookieFile);
        check($res['status'] === 200 && str_contains($res['body'], $needle), "GET {$path} -> 200 ({$needle})");
    }

    // 4. Live API endpoints
    $dashApi = httpReq('GET', $base . '/api/admin/dashboard', $cookieFile);
    $dashJson = json_decode($dashApi['body'], true);
    check($dashApi['status'] === 200 && ($dashJson['ok'] ?? false) === true && isset($dashJson['data']['charts']['daily_attendance']), 'GET /api/admin/dashboard -> 200 JSON charts & KPIs');

    $kioskApi = httpReq('GET', $base . '/api/kiosk/qr', $cookieFile);
    $kioskJson = json_decode($kioskApi['body'], true);
    check($kioskApi['status'] === 200 && ($kioskJson['ok'] ?? false) === true && str_contains((string) ($kioskJson['svg'] ?? ''), '<svg'), 'GET /api/kiosk/qr -> 200 JSON SVG QR');

    // 5. CSV export
    $csvRes = httpReq('GET', $base . '/admin/reports/export', $cookieFile);
    check($csvRes['status'] === 200 && str_contains($csvRes['body'], 'Kode Karyawan'), 'GET /admin/reports/export -> 200 CSV');

    // 6. Trigger backup via admin POST + CSRF
    $bkPage = httpReq('GET', $base . '/admin/backups', $cookieFile);
    $bkCsrf = extractCsrf($bkPage['body']);
    $bkPost = httpReq('POST', $base . '/admin/backups/create', $cookieFile, ['_csrf' => $bkCsrf]);
    check($bkPost['status'] === 302, 'POST /admin/backups/create -> 302 sukses');

    // 7. Employee PWA pages (accessible as authenticated user)
    foreach ([
        '/app' => 'Ringkasan Bulan Ini',
        '/app/scan' => 'Scan QR Absensi',
        '/app/history' => 'Riwayat Kehadiran',
        '/app/leaves' => 'Pengajuan Izin',
        '/app/profile' => 'Ganti Password',
    ] as $path => $needle) {
        $res = httpReq('GET', $base . $path, $cookieFile);
        check($res['status'] === 200 && str_contains($res['body'], $needle), "GET {$path} -> 200 ({$needle})");
    }

    // 8. Logout
    $profPage = httpReq('GET', $base . '/app/profile', $cookieFile);
    $outCsrf = extractCsrf($profPage['body']);
    $logoutRes = httpReq('POST', $base . '/logout', $cookieFile, ['_csrf' => $outCsrf]);
    check($logoutRes['status'] === 302 && str_contains($logoutRes['headers'], 'Location: /login'), 'POST /logout -> 302 ke /login');

    $afterLogout = httpReq('GET', $base . '/admin', $cookieFile);
    check($afterLogout['status'] === 302, 'GET /admin setelah logout -> 302 ditolak');
} finally {
    @unlink($cookieFile);
}

fwrite(STDOUT, "\nHTTP E2E: {$passed} LULUS | {$failed} GAGAL\n");
exit($failed > 0 ? 1 : 0);
