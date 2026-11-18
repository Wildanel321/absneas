<?php
$parts = preg_split('/\s+/', trim((string) ($user['full_name'] ?? 'K'))) ?: ['K'];
$initials = strtoupper(mb_substr($parts[0], 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
?>

<section class="card accent-top">
  <div class="user-cell" style="margin-bottom:16px;">
    <div class="avatar-initials" style="width:48px;height:48px;font-size:1.1rem;" aria-hidden="true"><?= $e($initials) ?></div>
    <div>
      <h1 style="margin:0;"><?= $e($user['full_name']) ?></h1>
      <div class="muted" style="font-size:0.88rem;">
        <?= $e($user['employee_code']) ?> &middot; <?= $e($user['department_name'] ?? 'Tanpa Departemen') ?>
        <?php if (!empty($user['position'])): ?>
          &middot; <?= $e($user['position']) ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <form method="post" action="/app/profile" class="form-grid">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

    <div class="field">
      <label for="profName">Nama Lengkap</label>
      <input id="profName" name="full_name" type="text" required value="<?= $e($user['full_name']) ?>">
    </div>

    <div class="field">
      <label for="profEmail">Email</label>
      <input id="profEmail" name="email" type="email" value="<?= $e($user['email'] ?? '') ?>" placeholder="nama@kantor.com">
    </div>

    <div class="field">
      <label for="profPhone">Nomor Telepon</label>
      <input id="profPhone" name="phone" type="tel" value="<?= $e($user['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx">
    </div>

    <div style="grid-column:1 / -1;">
      <button type="submit" class="btn btn-primary">Simpan Profil</button>
    </div>
  </form>
</section>

<section class="card">
  <h2>Ganti Password</h2>
  <form method="post" action="/app/profile/password" class="form-grid">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

    <div class="field">
      <label for="curPass">Password Saat Ini</label>
      <input id="curPass" name="current_password" type="password" required autocomplete="current-password">
    </div>

    <div class="field">
      <label for="newPass">Password Baru</label>
      <input id="newPass" name="new_password" type="password" required minlength="8" autocomplete="new-password">
    </div>

    <div class="field">
      <label for="confPass">Konfirmasi Password Baru</label>
      <input id="confPass" name="confirm_password" type="password" required minlength="8" autocomplete="new-password">
    </div>

    <div style="grid-column:1 / -1;">
      <button type="submit" class="btn btn-secondary">Perbarui Password</button>
    </div>
  </form>
</section>

<section class="card">
  <h2>Sesi Akun</h2>
  <p class="muted">Username login Anda: <strong><?= $e($user['username']) ?></strong></p>
  <form method="post" action="/logout">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    <button type="submit" class="btn btn-danger">Keluar dari Aplikasi</button>
  </form>
</section>
