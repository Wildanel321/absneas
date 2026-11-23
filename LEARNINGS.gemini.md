# Learnings & PRAR Cycle Log

## PRAR Cycle 1: Leaflet Interactive Map & PWA Header Simplification
- **Timestamp:** 2026-11-23T08:08:00+07:00
- **Perceive:** User requested an easier way to set office coordinates (having difficulty with latitude/longitude degrees) using Leaflet (free). User also requested removing the "Install Aplikasi" button in the PWA header to make it clean and applying `/antislop-layoutmobile`.
- **Reason:**
  - Standard GPS decimal coordinates can be confusing when entered manually. Leaflet with OpenStreetMap allows click-to-pin, drag-and-drop marker, interactive geofence circle, and auto-split for pasted Google Maps coordinates.
  - Content Security Policy (CSP) in `Response.php` must explicitly allow `https://*.tile.openstreetmap.org` for map tiles while keeping Leaflet scripts local (`script-src 'self'`).
  - Fast asset downloads achieved using `aria2c` directly into `public/assets/js/vendor/` and `public/assets/css/`.
  - PWA header in `resources/views/layouts/employee.php` contains `#pwaInstallBtn` which crowds the mobile header. Removing it streamlines mobile layout.
- **Act:**
  - Downloaded Leaflet v1.9.4 assets and marker images using `aria2c`.
  - Configured CSP in `Response.php` to permit OpenStreetMap tile servers.
  - Cleaned up employee PWA header in `employee.php` by removing the "Install Aplikasi" button and enforcing 44px min tap targets.
  - Implemented interactive Leaflet map with draggable pin, geofence circle, GPS autofill, and Google Maps coordinate paste parser in `settings.php` and `app.js`.
  - Added test coverage in `tests/run.php` verifying CSP, map container, Leaflet assets, and clean PWA header.
- **Refine:**
  - Executed test suite with `wsl php tests/run.php`: 65 passed, 0 failed.
  - Verified antislop-layoutmobile checklist (touch target sizing, zero overflow, responsive layout).

## PRAR Cycle 2: Saturday Schedule, Selfie Capture & Print-Friendly Reports
- **Timestamp:** 2026-11-23T08:46:00+07:00
- **Perceive:** User requested: (1) Work schedule adjustments: Mon-Fri checkout at 14:00 (jam 2), Saturday checkout at 12:00 (jam 12), check-in from 07:45 to 08:05 on-time; (2) Quick selfie verification during QR scan; (3) Clean print-ready report format.
- **Reason:**
  - Different closing times on Saturday vs weekdays require dynamic day-of-week detection in `QrTokenService` and `AdminSettingsController`.
  - Check-in window starting at 07:45 with on-time tolerance until 08:05 equals 20 minutes late tolerance from 07:45.
  - To prevent server bloat, selfie photos are compressed to 320x240 JPEG (~20KB) on the client canvas, saved to `storage/photos/YYYY-MM/`, and served via authenticated route `/photo/{folder}/{file}`.
  - Print reports require pure CSS `@media print` rules hiding navigation and action bars while formatting official headers, signature blocks, and selfie thumbnails.
- **Act:**
  - Added migration `002_schedule_and_selfie.sql`.
  - Updated `AttendanceService`, `QrTokenService`, `SettingModel`, and `AdminSettingsController`.
  - Updated `EmployeeController` and `scanner.js` with selfie capture and front-camera guide.
  - Updated `reports.php` and `app.css` for print layouts and signature blocks.
- **Refine:**
  - Extended `tests/run.php` to verify Saturday vs weekday schedules, 08:05 on-time vs late, selfie persistence, and report print elements.
  - Executed test suite: 77 passed, 0 failed.

## PRAR Cycle 3: Office TV Attendance Notification & Voice Announcement
- **Timestamp:** 2026-11-23T08:56:00+07:00
- **Perceive:** User requested adding live visual pop-up notifications and sound announcements on the office TV display (`/kiosk`), e.g. "user123 telah berhasil absen" when an employee records attendance.
- **Reason:**
  - Modern browsers require user interaction before allowing audio playback (Autoplay Policy). A sound toggle button (`#kioskSoundBtn`) and window gesture listeners unlock audio frictionlessly.
  - Relying on external audio files can cause latency or network failures; Web Audio API (`AudioContext`) allows instant, zero-asset synthesis of a melodic 2-tone "Ding-Dong" chime (E5 -> A5).
  - Speech synthesis is natively supported across modern TV and desktop browsers via `window.speechSynthesis` with Indonesian voice (`id-ID`).
  - Polling `/api/kiosk/activity` every 4 seconds ensures attendance events appear within 2-3 seconds without overburdening the database.
- **Act:**
  - Implemented `getRecentAttendances()` in `KioskController.php` uniting check-in and check-out events ordered chronologically.
  - Added fast polling endpoint `GET /api/kiosk/activity` and updated `GET /api/kiosk/qr`.
  - Added `requireAuthOrKioskAccess()` to `AuthMiddleware.php` and `Bootstrap.php` for seamless selfie thumbnail rendering on TV monitors.
  - Created high-visibility popup overlay `#kioskAttendanceAlert` and real-time recent attendance feed in `resources/views/kiosk/display.php` and `public/assets/css/app.css`.
  - Implemented audio chime, Indonesian speech announcement, and 4-second activity polling in `public/assets/js/kiosk.js`.
  - Added comprehensive automated tests in `tests/run.php`.
- **Refine:**
  - Executed test suite with `wsl php tests/run.php`: **85 passed, 0 failed**.
  - Verified local dev server is running and healthy on `http://localhost:8000`.
  - Refined speech synthesis phrasing and UI alert copy to strictly use employee name or fallback "Karyawan" followed by "berhasil absen masuk" and "berhasil absen pulang" (eliminating generic placeholder words like "User").
