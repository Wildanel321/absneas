-- Migrasi 002: Kolom foto selfie absensi dan pengaturan jadwal khusus Sabtu
ALTER TABLE attendances ADD COLUMN checkin_photo TEXT NULL;

INSERT OR IGNORE INTO settings (key, value) VALUES ('saturday_work_end_time', '12:00');
UPDATE settings SET value = '07:45' WHERE key = 'work_start_time' AND value = '08:00';
UPDATE settings SET value = '14:00' WHERE key = 'work_end_time' AND value = '17:00';
UPDATE settings SET value = '20' WHERE key = 'late_tolerance_minutes' AND value = '15';
UPDATE settings SET value = '[1,2,3,4,5,6]' WHERE key = 'working_days' AND value = '[1,2,3,4,5]';
