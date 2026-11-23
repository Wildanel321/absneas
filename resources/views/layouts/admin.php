<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0F1D3A">
  <title><?= $e($title ?? 'Admin - Smart Attendance') ?></title>
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
  <link rel="apple-touch-icon" href="/icons/icon-192.png">
  <link rel="stylesheet" href="/assets/css/app.css">
  <?php if (($activeNav ?? '') === 'settings'): ?>
    <link rel="stylesheet" href="/assets/css/leaflet.css">
  <?php endif; ?>
</head>
<body>
  <a href="#mainContent" class="skip-link">Lewati ke konten utama</a>
  <div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>

  <div class="admin-shell">
    <aside id="adminSidebar" class="sidebar" aria-label="Navigasi Administrator">
      <div class="sidebar-brand">
        <img src="/icons/icon.svg" alt="" width="38" height="38" class="brand-mark">
        <div>
          <div class="sidebar-brand-title">Smart Attendance</div>
          <div class="sidebar-brand-sub"><?= $e($officeName ?? 'Kantor Pusat') ?></div>
        </div>
      </div>

      <nav class="sidebar-nav" aria-label="Menu Utama">
        <a href="/admin" class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>" <?= ($activeNav ?? '') === 'dashboard' ? 'aria-current="page"' : '' ?>>
          <span class="nav-link-inner">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="8" height="8"/><rect x="13" y="3" width="8" height="5"/><rect x="13" y="10" width="8" height="11"/><rect x="3" y="13" width="8" height="8"/></svg>
            <span>Dashboard</span>
          </span>
        </a>
        <a href="/admin/employees" class="nav-link <?= ($activeNav ?? '') === 'employees' ? 'active' : '' ?>" <?= ($activeNav ?? '') === 'employees' ? 'aria-current="page"' : '' ?>>
          <span class="nav-link-inner">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
            <span>Karyawan</span>
          </span>
        </a>
        <a href="/admin/leaves" class="nav-link <?= ($activeNav ?? '') === 'leaves' ? 'active' : '' ?>" <?= ($activeNav ?? '') === 'leaves' ? 'aria-current="page"' : '' ?>>
          <span class="nav-link-inner">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span>Izin &amp; Sakit</span>
          </span>
          <?php if (($pendingLeaves ?? 0) > 0): ?>
            <span class="nav-counter tabular"><?= (int) $pendingLeaves ?></span>
          <?php endif; ?>
        </a>
        <a href="/admin/reports" class="nav-link <?= ($activeNav ?? '') === 'reports' ? 'active' : '' ?>" <?= ($activeNav ?? '') === 'reports' ? 'aria-current="page"' : '' ?>>
          <span class="nav-link-inner">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
            <span>Laporan</span>
          </span>
        </a>
        <a href="/admin/settings" class="nav-link <?= ($activeNav ?? '') === 'settings' ? 'active' : '' ?>" <?= ($activeNav ?? '') === 'settings' ? 'aria-current="page"' : '' ?>>
          <span class="nav-link-inner">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span>Aturan &amp; GPS</span>
          </span>
        </a>
        <a href="/admin/backups" class="nav-link <?= ($activeNav ?? '') === 'backups' ? 'active' : '' ?>" <?= ($activeNav ?? '') === 'backups' ? 'aria-current="page"' : '' ?>>
          <span class="nav-link-inner">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
            <span>Backup &amp; Restore</span>
          </span>
        </a>
        <a href="/admin/audit-logs" class="nav-link <?= ($activeNav ?? '') === 'audit' ? 'active' : '' ?>" <?= ($activeNav ?? '') === 'audit' ? 'aria-current="page"' : '' ?>>
          <span class="nav-link-inner">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Log Audit</span>
          </span>
        </a>
        <a href="/kiosk" target="_blank" rel="noopener" class="nav-link">
          <span class="nav-link-inner">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM18 18h3v3h-3zM14 19h2v2h-2z"/></svg>
            <span>Monitor QR</span>
          </span>
        </a>
      </nav>

      <div class="sidebar-footer">
        <div class="sidebar-user">
          <strong><?= $e($user['full_name'] ?? 'Administrator') ?></strong>
          <span><?= $e($user['employee_code'] ?? '') ?> (Admin)</span>
        </div>
        <button type="button" id="closeSidebarBtn" class="sidebar-btn mobile-menu-btn">Tutup Menu</button>
        <a href="/app" class="sidebar-btn">Mode Karyawan (PWA)</a>
        <form method="post" action="/logout">
          <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
          <button type="submit" class="sidebar-btn">Keluar</button>
        </form>
      </div>
    </aside>

    <div class="admin-content-wrap">
      <header class="topbar">
        <div class="topbar-left">
          <button type="button" id="openSidebarBtn" class="btn btn-secondary btn-sm mobile-menu-btn" aria-controls="adminSidebar" aria-expanded="false">
            Menu
          </button>
          <span class="muted"><?= $e($officeName ?? 'Kantor Pusat') ?></span>
        </div>
        <div class="topbar-actions">
          <button type="button" class="btn btn-ghost btn-sm" data-theme-toggle aria-pressed="false">
            <span data-theme-label>Mode Gelap</span>
          </button>
          <a href="/kiosk" target="_blank" rel="noopener" class="btn btn-navy btn-sm">Buka Monitor QR</a>
        </div>
      </header>

      <main id="mainContent" class="main-container">
        <?php foreach ($flashes ?? [] as $flash): ?>
          <div class="alert alert-<?= $e($flash['type']) ?>" role="status"><?= $e($flash['message']) ?></div>
        <?php endforeach; ?>

        <?= $content ?>
      </main>
    </div>
  </div>

  <?php if (($activeNav ?? '') === 'settings'): ?>
    <script src="/assets/js/vendor/leaflet.js" defer></script>
  <?php endif; ?>
  <script src="/assets/js/app.js" defer></script>
  <?php if (($activeNav ?? '') === 'dashboard'): ?>
    <script src="/assets/js/charts.js" defer></script>
  <?php endif; ?>
</body>
</html>
