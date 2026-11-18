<section class="card accent-top">
  <div class="card-header">
    <div class="card-title-group">
      <h1>Manajemen Karyawan</h1>
      <p>Kelola akun karyawan, departemen, role, dan status aktif/nonaktif (dirancang untuk tim hingga 30 orang).</p>
    </div>
    <div class="action-row">
      <button type="button" class="btn btn-primary" data-modal-open="addEmployeeModal">Tambah Karyawan</button>
      <button type="button" class="btn btn-secondary" data-modal-open="departmentsModal">Kelola Departemen</button>
    </div>
  </div>

  <form method="get" action="/admin/employees" class="filter-bar" style="margin-bottom:16px;">
    <div class="field">
      <label for="empSearch">Cari Nama / Kode / Username</label>
      <input id="empSearch" name="search" type="search" value="<?= $e($filters['search']) ?>" placeholder="Ketik kata kunci...">
    </div>
    <div class="field">
      <label for="empDept">Departemen</label>
      <select id="empDept" name="department_id">
        <option value="">Semua Departemen</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= $filters['department_id'] === (string) $d['id'] ? 'selected' : '' ?>><?= $e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="empRole">Role</label>
      <select id="empRole" name="role">
        <option value="">Semua Role</option>
        <option value="employee" <?= $filters['role'] === 'employee' ? 'selected' : '' ?>>Karyawan</option>
        <option value="admin" <?= $filters['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
      </select>
    </div>
    <div class="field">
      <label for="empStatus">Status Akun</label>
      <select id="empStatus" name="is_active">
        <option value="">Semua Status</option>
        <option value="1" <?= $filters['is_active'] === '1' ? 'selected' : '' ?>>Aktif</option>
        <option value="0" <?= $filters['is_active'] === '0' ? 'selected' : '' ?>>Nonaktif</option>
      </select>
    </div>
    <div class="action-row">
      <button type="submit" class="btn btn-secondary">Filter</button>
      <a href="/admin/employees" class="btn btn-ghost">Reset</a>
    </div>
  </form>

  <?php if (empty($employees)): ?>
    <div class="empty-state">
      <strong>Tidak ada data karyawan</strong>
      <span>Klik Tambah Karyawan untuk mendaftarkan anggota tim baru.</span>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Karyawan</th>
            <th>Username</th>
            <th>Departemen &amp; Jabatan</th>
            <th>Role</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($employees as $emp):
            $parts = preg_split('/\s+/', trim((string) $emp['full_name'])) ?: ['K'];
            $ini = strtoupper(mb_substr($parts[0], 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
            $isActive = (int) $emp['is_active'] === 1;
          ?>
            <tr>
              <td>
                <div class="user-cell">
                  <span class="avatar-initials" aria-hidden="true"><?= $e($ini) ?></span>
                  <div>
                    <strong><?= $e($emp['full_name']) ?></strong>
                    <div class="muted tabular"><?= $e($emp['employee_code']) ?></div>
                  </div>
                </div>
              </td>
              <td><?= $e($emp['username']) ?></td>
              <td>
                <div><?= $e($emp['department_name'] ?? 'Tanpa Departemen') ?></div>
                <?php if (!empty($emp['position'])): ?>
                  <div class="muted" style="font-size:0.8rem;"><?= $e($emp['position']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?= $emp['role'] === 'admin' ? 'badge-info' : 'badge-neutral' ?>">
                  <?= $emp['role'] === 'admin' ? 'Administrator' : 'Karyawan' ?>
                </span>
              </td>
              <td>
                <span class="badge <?= $isActive ? 'badge-success' : 'badge-danger' ?>">
                  <?= $isActive ? 'Aktif' : 'Nonaktif' ?>
                </span>
              </td>
              <td>
                <div class="action-row">
                  <a href="/admin/employees/<?= (int) $emp['id'] ?>" class="btn btn-secondary btn-sm">Detail &amp; Edit</a>
                  <form method="post" action="/admin/employees/<?= (int) $emp['id'] ?>/toggle">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <button type="submit" class="btn btn-ghost btn-sm"><?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                  </form>
                  <?php if ((int) $emp['id'] !== (int) $user['id']): ?>
                    <form method="post" action="/admin/employees/<?= (int) $emp['id'] ?>/delete" data-confirm="Hapus akun <?= $e($emp['full_name']) ?> beserta seluruh riwayat absensinya?">
                      <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                      <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- Modal Tambah Karyawan -->
<div id="addEmployeeModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="addEmpTitle" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-header">
      <h2 id="addEmpTitle">Tambah Karyawan Baru</h2>
      <button type="button" class="btn btn-ghost btn-sm" data-modal-close aria-label="Tutup dialog">Tutup</button>
    </div>

    <form method="post" action="/admin/employees" class="form-grid">
      <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">

      <div class="field">
        <label for="newCodePreview">Kode Karyawan Otomatis</label>
        <input id="newCodePreview" type="text" value="<?= $e($nextCode) ?>" disabled>
        <span class="hint">Dibuat otomatis oleh sistem saat disimpan.</span>
      </div>

      <div class="field">
        <label for="newRole">Role Akun</label>
        <select id="newRole" name="role">
          <option value="employee">Karyawan</option>
          <option value="admin">Administrator</option>
        </select>
      </div>

      <div class="field" style="grid-column:1 / -1;">
        <label for="newFullName">Nama Lengkap</label>
        <input id="newFullName" name="full_name" type="text" required placeholder="Nama lengkap karyawan">
      </div>

      <div class="field">
        <label for="newUsername">Username Login</label>
        <input id="newUsername" name="username" type="text" required pattern="[a-zA-Z0-9._-]{3,40}" placeholder="contoh: nama.karyawan">
      </div>

      <div class="field">
        <label for="newPassword">Password Awal</label>
        <input id="newPassword" name="password" type="password" required minlength="8" placeholder="Minimal 8 karakter">
      </div>

      <div class="field">
        <label for="newDept">Departemen</label>
        <select id="newDept" name="department_id">
          <option value="">Pilih Departemen</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= (int) $d['id'] ?>"><?= $e($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="newPos">Jabatan</label>
        <input id="newPos" name="position" type="text" placeholder="Jabatan atau posisi">
      </div>

      <div class="field">
        <label for="newEmail">Email (opsional)</label>
        <input id="newEmail" name="email" type="email" placeholder="email@example.com">
      </div>

      <div class="field">
        <label for="newPhone">Telepon (opsional)</label>
        <input id="newPhone" name="phone" type="tel" placeholder="08xxxxxxxxxx">
      </div>

      <div class="action-row" style="grid-column:1 / -1;justify-content:flex-end;margin-top:6px;">
        <button type="button" class="btn btn-ghost" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Karyawan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Departemen -->
<div id="departmentsModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="deptModalTitle" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-header">
      <h2 id="deptModalTitle">Kelola Departemen</h2>
      <button type="button" class="btn btn-ghost btn-sm" data-modal-close aria-label="Tutup dialog">Tutup</button>
    </div>

    <form method="post" action="/admin/departments" class="action-row" style="margin-bottom:16px;">
      <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
      <div class="field" style="flex:1;">
        <label for="deptNameInput">Tambah Departemen Baru</label>
        <input id="deptNameInput" name="name" type="text" required minlength="2" maxlength="60" placeholder="Nama departemen">
      </div>
      <div style="align-self:flex-end;">
        <button type="submit" class="btn btn-primary">Tambah</button>
      </div>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Departemen</th>
            <th>Karyawan</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($departments as $d): ?>
            <tr>
              <td><strong><?= $e($d['name']) ?></strong></td>
              <td class="tabular"><?= (int) $d['employee_count'] ?></td>
              <td>
                <form method="post" action="/admin/departments/<?= (int) $d['id'] ?>/delete" data-confirm="Hapus departemen <?= $e($d['name']) ?>?">
                  <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
