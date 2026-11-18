<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0F1D3A">
  <title><?= $e($title ?? 'Masuk - Smart Attendance') ?></title>
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
  <link rel="apple-touch-icon" href="/icons/icon-192.png">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-body">
  <main class="auth-shell">
    <section class="card auth-card accent-top">
      <div class="auth-brand">
        <img src="/icons/icon.svg" alt="Ikon Smart Attendance" width="40" height="40" class="brand-mark">
        <div>
          <h1 style="font-size:1.3rem;margin:0;">Smart Attendance</h1>
          <div class="muted" style="font-size:0.85rem;"><?= $e($officeName ?? 'Kantor Pusat') ?></div>
        </div>
      </div>

      <?php foreach ($flashes ?? [] as $flash): ?>
        <div class="alert alert-<?= $e($flash['type']) ?>" role="status"><?= $e($flash['message']) ?></div>
      <?php endforeach; ?>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error" role="alert"><?= $e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="/login" class="form-grid" style="grid-template-columns:1fr;">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

        <div class="field">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" required autocomplete="username" value="<?= $e($oldUsername ?? '') ?>" placeholder="Masukkan username Anda">
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Masukkan password Anda">
        </div>

        <button type="submit" class="btn btn-primary btn-block">Masuk ke Sistem</button>
      </form>

      <div class="auth-footer">
        <span class="muted">Gunakan akun dari administrator kantor.</span>
        <button type="button" class="btn btn-ghost btn-sm" data-theme-toggle aria-pressed="false">
          <span data-theme-label>Mode Gelap</span>
        </button>
      </div>
    </section>
  </main>
  <script src="/assets/js/app.js" defer></script>
</body>
</html>
