-- Smart Attendance: Initial SQLite Schema

CREATE TABLE IF NOT EXISTS migrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT NOT NULL UNIQUE,
    executed_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_code TEXT NOT NULL UNIQUE,
    full_name TEXT NOT NULL,
    username TEXT NOT NULL UNIQUE,
    email TEXT DEFAULT '',
    phone TEXT DEFAULT '',
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('admin', 'employee')) DEFAULT 'employee',
    department_id INTEGER NULL REFERENCES departments(id) ON DELETE SET NULL,
    position TEXT DEFAULT '',
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    last_login_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE INDEX IF NOT EXISTS idx_users_role_active ON users(role, is_active);
CREATE INDEX IF NOT EXISTS idx_users_department ON users(department_id);

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS holidays (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    holiday_date TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS qr_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash TEXT NOT NULL UNIQUE,
    token_prefix TEXT NOT NULL,
    mode TEXT NOT NULL CHECK (mode IN ('checkin', 'checkout')),
    issued_at INTEGER NOT NULL,
    expires_at INTEGER NOT NULL,
    revoked_at INTEGER NULL,
    created_by_ip TEXT DEFAULT ''
);

CREATE INDEX IF NOT EXISTS idx_qr_tokens_mode_exp ON qr_tokens(mode, expires_at);

CREATE TABLE IF NOT EXISTS qr_token_usages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    qr_token_id INTEGER NOT NULL REFERENCES qr_tokens(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    used_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    UNIQUE (qr_token_id, user_id)
);

CREATE TABLE IF NOT EXISTS attendances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    attendance_date TEXT NOT NULL,
    checkin_at TEXT NULL,
    checkin_lat REAL NULL,
    checkin_lng REAL NULL,
    checkin_accuracy_m REAL NULL,
    checkin_distance_m REAL NULL,
    checkout_at TEXT NULL,
    checkout_lat REAL NULL,
    checkout_lng REAL NULL,
    checkout_accuracy_m REAL NULL,
    checkout_distance_m REAL NULL,
    late_minutes INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL CHECK (status IN ('on_time', 'late', 'leave', 'sick', 'absent')) DEFAULT 'on_time',
    notes TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    UNIQUE (user_id, attendance_date)
);

CREATE INDEX IF NOT EXISTS idx_attendances_date ON attendances(attendance_date);
CREATE INDEX IF NOT EXISTS idx_attendances_user_date ON attendances(user_id, attendance_date);
CREATE INDEX IF NOT EXISTS idx_attendances_status ON attendances(status);

CREATE TABLE IF NOT EXISTS leave_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type TEXT NOT NULL CHECK (type IN ('leave', 'sick')),
    start_date TEXT NOT NULL,
    end_date TEXT NOT NULL,
    reason TEXT NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('pending', 'approved', 'rejected')) DEFAULT 'pending',
    reviewed_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at TEXT NULL,
    review_note TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE INDEX IF NOT EXISTS idx_leave_requests_user_status ON leave_requests(user_id, status);
CREATE INDEX IF NOT EXISTS idx_leave_requests_dates ON leave_requests(start_date, end_date);

CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address TEXT NOT NULL,
    username TEXT NOT NULL,
    attempted_at INTEGER NOT NULL,
    success INTEGER NOT NULL DEFAULT 0 CHECK (success IN (0, 1))
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_lookup ON login_attempts(ip_address, username, attempted_at);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT DEFAULT '',
    details_json TEXT DEFAULT '{}',
    ip_address TEXT DEFAULT '',
    user_agent TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at DESC);

-- Default departments
INSERT OR IGNORE INTO departments (name) VALUES
    ('Umum'),
    ('Operasional'),
    ('Teknologi'),
    ('Keuangan'),
    ('SDM');

-- Default attendance settings
INSERT OR IGNORE INTO settings (key, value) VALUES
    ('office_name', 'Kantor Pusat'),
    ('office_lat', '-6.200000'),
    ('office_lng', '106.816666'),
    ('geofence_radius_m', '100'),
    ('max_gps_accuracy_m', '150'),
    ('work_start_time', '08:00'),
    ('work_end_time', '17:00'),
    ('late_tolerance_minutes', '15'),
    ('checkout_open_before_minutes', '30'),
    ('qr_refresh_seconds', '30'),
    ('qr_token_ttl_seconds', '45'),
    ('working_days', '[1,2,3,4,5]'),
    ('timezone', 'Asia/Jakarta');
