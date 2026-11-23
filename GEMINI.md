# Smart Attendance (absneas) - Project Context

## 1. Project Purpose
Smart Attendance is a modern, lightweight, on-premise employee attendance system tailored for small offices and teams (up to 30 employees). It runs smoothly on modest hardware like a Raspberry Pi 3 or cloud VPS, combining:
- Mobile PWA for employees (check-in, check-out, leave requests, attendance history).
- Kiosk mode with dynamic rotating QR codes for secure physical office check-ins.
- Administrative dashboard with analytics, employee management, geofence settings, audit logs, and USB backups.

## 2. Specific Architecture
- **Backend:** Pure PHP 8.2+ without bloated frameworks for minimal memory footprint and zero cold-start latency.
- **Database:** SQLite with WAL mode (`PRAGMA journal_mode = WAL`) and foreign key constraints enabled.
- **Security:** Anti-tampering, CSRF protection, brute-force rate limiting, geofencing (Haversine formula), HMAC-signed dynamic QR tokens with TTL, and Content Security Policy (CSP).
- **Frontend:** Server-rendered views (`resources/views`) with vanilla JavaScript (`public/assets/js`) and native CSS (`public/assets/css`). No heavy external JavaScript frameworks.
- **Maps:** Leaflet.js with OpenStreetMap (fully free, zero-cost, no API key required).

## 3. Key Files & Directories
- `app/Core/`: Core application foundation (Bootstrap, Router, Request, Response, Database, Session, Csrf, Validator).
- `app/Controllers/`: Request handlers (Admin, Auth, Employee, Kiosk).
- `app/Models/`: Data access objects (User, Attendance, Setting, Holiday, LeaveRequest, AuditLog).
- `app/Services/`: Business logic (GeoService, AttendanceService, QrTokenService, BackupService, AnalyticsService).
- `resources/views/`: PHP template views for admin, employee, auth, and kiosk.
- `public/`: Web server root, assets (CSS, JS, icons), service worker (`sw.js`).
- `tests/`: Automated test suite (`run.php`).

## 4. Local Setup & Testing
- Run test suite: `wsl php tests/run.php`
- Local web server: `php -S 0.0.0.0:8000 -t public`
