<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0F1D3A">
  <title><?= $e($title ?? 'Smart Attendance') ?></title>
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
  <link rel="apple-touch-icon" href="/icons/icon-192.png">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
  <a href="#empMain" class="skip-link">Lewati ke konten utama</a>

  <div class="emp-shell">
    <header class="emp-header">
      <div class="emp-header-brand">
        <img src="/icons/icon.svg" alt="" width="34" height="34" class="brand-mark">
        <div>
          <div class="emp-header-title">Smart Attendance</div>
          <div class="emp-header-sub"><?= $e($officeName ?? 'Kantor Pusat') ?></div>
        </div>
      </div>
      <div class="action-row">
        <button type="button" id="pwaInstallBtn" class="btn btn-primary btn-sm" hidden>Install Aplikasi</button>
        <button type="button" class="btn btn-sm" style="background:rgba(255,255,255,0.12);color:#fff;border-color:rgba(255,255,255,0.22);" data-theme-toggle aria-pressed="false">
          <span data-theme-label>Mode Gelap</span>
        </button>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
          <a href="/admin" class="btn btn-sm" style="background:#5B3FD6;color:#fff;">Panel Admin</a>
        <?php endif; ?>
      </div>
    </header>

    <main id="empMain" class="emp-main">
      <?php foreach ($flashes ?? [] as $flash): ?>
        <div class="alert alert-<?= $e($flash['type']) ?>" role="status"><?= $e($flash['message']) ?></div>
      <?php endforeach; ?>

      <?= $content ?>
    </main>

    <nav class="emp-bottom-nav" aria-label="Navigasi Karyawan">
      <a href="/app" class="bottom-nav-link <?= ($activeNav ?? '') === 'home' ? 'active' : '' ?>" <?= ($activeNav ?? '') === 'home' ? 'aria-current="page"' : '' ?>>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 10.5L12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
        <span>Beranda</span>
      </a>
      <a href="/app/scan" class="bottom-nav-link <?= ($activeNav ?? '') === 'scan' ? 'active' : '' ?>" <?= ($activeNav ?? '') === 'scan' ? 'aria-current="page"' : '' ?>>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM18 18h3v3h-3z"/></svg>
        <span>Scan QR</span>
      </a>
      <a href="/app/history" class="bottom-nav-link <?= ($activeNav ?? '') === 'history' ? 'active' : '' ?>" <?= ($activeNav ?? '') === 'history' ? 'aria-current="page"' : '' ?>>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
        <span>Riwayat</span>
      </a>
      <a href="/app/leaves" class="bottom-nav-link <?= ($activeNav ?? '') === 'leaves' ? 'active' : '' ?>" <?= ($activeNav ?? '') === 'leaves' ? 'aria-current="page"' : '' ?>>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Izin/Sakit</span>
      </a>
      <a href="/app/profile" class="bottom-nav-link <?= ($activeNav ?? '') === 'profile' ? 'active' : '' ?>" <?= ($activeNav ?? '') === 'profile' ? 'aria-current="page"' : '' ?>>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2"/></svg>
        <span>Profil</span>
      </a>
    </nav>
  </div>

  <script src="/assets/js/app.js" defer></script>
  <?php if (($activeNav ?? '') === 'scan'): ?>
    <script src="/assets/js/vendor/jsqr.js" defer></script>
    <script src="/assets/js/scanner.js" defer></script>
  <?php endif; ?>
</body>
</html>
