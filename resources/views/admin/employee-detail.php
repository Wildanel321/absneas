<?php use App\Services\AttendanceService; ?>

<div class="action-row">
  <a href="/admin/employees" class="btn btn-ghost btn-sm">Kembali ke Daftar Karyawan</a>
</div>

<section class="split-grid">
  <div class="card accent-top">
    <div class="card-header">
      <div class="card-title-group">
        <h1><?= $e($employee['full_name']) ?></h1>
        <p class="tabular">Kode: <?= $e($employee['employee_code']) ?> &middot; Terdaftar: <?= $e($employee['created_at']) ?></p>
      </div>
      <span class="badge <?= (int) $employee['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>">
        <?= (int) $employee['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
      </span>
    </div>

    <form method="post" action="/admin/employees/<?= (int) $employee['id'] ?>/update" class="form-grid">
      <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

      <div class="field">
        <label for="editName">Nama Lengkap</label>
        <input id="editName" name="full_name" type="text" required value="<?= $e($employee['full_name']) ?>">
      </div>

      <div class="field">
        <label for="editUser">Username</label>
        <input id="editUser" name="username" type="text" required value="<?= $e($employee['username']) ?>">
      </div>

      <div class="field">
        <label for="editDept">Departemen</label>
        <select id="editDept" name="department_id">
          <option value="">Tanpa Departemen</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= (int) $d['id'] ?>" <?= (int) ($employee['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>>
              <?= $e($d['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="editPos">Jabatan</label>
        <input id="editPos" name="position" type="text" value="<?= $e($employee['position'] ?? '') ?>">
      </div>

      <div class="field">
        <label for="editEmail">Email</label>
        <input id="editEmail" name="email" type="email" value="<?= $e($employee['email'] ?? '') ?>">
      </div>

      <div class="field">
        <label for="editPhone">Telepon</label>
        <input id="editPhone" name="phone" type="tel" value="<?= $e($employee['phone'] ?? '') ?>">
      </div>

      <div class="field">
        <label for="editRole">Role</label>
        <select id="editRole" name="role">
          <option value="employee" <?= $employee['role'] === 'employee' ? 'selected' : '' ?>>Karyawan</option>
          <option value="admin" <?= $employee['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
        </select>
      </div>

      <div class="field" style="justify-content:flex-end;">
        <label class="checkbox-chip">
          <input type="checkbox" name="is_active" value="1" <?= (int) $employee['is_active'] === 1 ? 'checked' : '' ?>>
          <span>Akun Aktif</span>
        </label>
      </div>

      <div style="grid-column:1 / -1;">
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Reset Password Karyawan</h2>
    <p class="muted">Gunakan formulir ini jika karyawan lupa password. Password disimpan menggunakan <code>password_hash()</code>.</p>
    <form method="post" action="/admin/employees/<?= (int) $employee['id'] ?>/reset-password" class="form-grid" style="grid-template-columns:1fr;">
      <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
      <div class="field">
        <label for="resetPass">Password Baru</label>
        <input id="resetPass" name="new_password" type="password" required minlength="8" placeholder="Minimal 8 karakter">
      </div>
      <button type="submit" class="btn btn-secondary">Reset Password</button>
    </form>
  </div>
</section>

<section class="card">
  <div class="card-header">
    <h2>Riwayat Absensi Karyawan</h2>
    <form method="get" action="/admin/employees/<?= (int) $employee['id'] ?>" class="action-row">
      <label for="histMonth" class="sr-only">Pilih Bulan</label>
      <input id="histMonth" name="month" type="month" value="<?= $e($month) ?>" style="width:auto;">
      <button type="submit" class="btn btn-secondary btn-sm">Tampilkan</button>
    </form>
  </div>

  <?php if (empty($history)): ?>
    <div class="empty-state">
      <strong>Belum ada riwayat absensi pada bulan <?= $e($month) ?></strong>
      <span>Pilih bulan lain untuk melihat arsip kehadiran karyawan ini.</span>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Masuk</th>
            <th>Pulang</th>
            <th>Terlambat</th>
            <th>Jarak GPS</th>
            <th>Akurasi</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h):
            $desc = AttendanceService::describeStatus($h);
          ?>
            <tr>
              <td class="tabular"><?= $e($h['attendance_date']) ?></td>
              <td class="tabular"><?= !empty($h['checkin_at']) ? $e(substr((string) $h['checkin_at'], 11, 8)) : '-' ?></td>
              <td class="tabular"><?= !empty($h['checkout_at']) ? $e(substr((string) $h['checkout_at'], 11, 8)) : '-' ?></td>
              <td class="tabular"><?= (int) $h['late_minutes'] ?> mnt</td>
              <td class="tabular"><?= $h['checkin_distance_m'] !== null ? $e(round((float) $h['checkin_distance_m'], 1)) . ' m' : '-' ?></td>
              <td class="tabular"><?= $h['checkin_accuracy_m'] !== null ? '+/- ' . $e(round((float) $h['checkin_accuracy_m'])) . ' m' : '-' ?></td>
              <td><span class="badge badge-<?= $e($desc['tone']) ?>"><?= $e($desc['label']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
