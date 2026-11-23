# Project Backlog & Implementation Tasks

## Sprint 1: Interactive Office Geofencing Map & Mobile UI Hygiene

- [x] **Task 1.1:** Download and vendor Leaflet v1.9.4 assets locally (`leaflet.js`, `leaflet.css`, marker images) using `aria2c`.
- [x] **Task 1.2:** Update Content-Security-Policy in `app/Core/Response.php` to whitelist OpenStreetMap tile domains (`https://*.tile.openstreetmap.org`).
- [x] **Task 1.3:** Remove redundant "Install Aplikasi" button from employee PWA layout (`resources/views/layouts/employee.php`) to reduce mobile header clutter.
- [x] **Task 1.4:** Integrate Leaflet map container and Google Maps paste parser in `resources/views/admin/settings.php`.
- [x] **Task 1.5:** Implement two-way coordinate binding, radius circle visualization, and GPS autofill sync in `public/assets/js/app.js`.
- [x] **Task 1.6:** Apply `antislop-layoutmobile` audit: ensure 44x44px minimum tap targets, no horizontal overflow, responsive form wrapping.
- [x] **Task 1.7:** Execute test suite (`wsl php tests/run.php`) to ensure 100% pass rate (65 tests passed).

## Sprint 2: Custom Work Schedules, Check-in Selfie Verification & Print-Ready Reports

- [x] **Task 2.1:** Database migration `002_schedule_and_selfie.sql` for `checkin_photo` column and Saturday schedule setting.
- [x] **Task 2.2:** Configure custom schedules: check-in 07:45 to 08:05 on-time window (20 min tolerance), Mon-Fri checkout at 14:00, Saturday checkout at 12:00.
- [x] **Task 2.3:** Update `QrTokenService` for dynamic Saturday work end time and checkout switch time.
- [x] **Task 2.4:** Implement selfie snapshot capture workflow in `scanner.js` and `scan.php` with front-camera guide.
- [x] **Task 2.5:** Implement backend photo decoding, secure storage in `storage/photos/`, and route `/photo/{folder}/{file}` in `EmployeeController` and `Bootstrap`.
- [x] **Task 2.6:** Add print-ready report layout with official header, signature blocks, and `@media print` CSS in `reports.php` and `app.css`.
- [x] **Task 2.7:** Verify with test suite: 77 tests passed, 0 failed.

## Sprint 3: Office TV Attendance Notification & Voice Announcement
- [x] **Task 3.1:** Add live attendance event feed to `/api/kiosk/qr` and dedicated fast polling endpoint `/api/kiosk/activity`.
- [x] **Task 3.2:** Update `AuthMiddleware` and `Bootstrap.php` with `requireAuthOrKioskAccess()` to securely display selfie photos on office TV monitors.
- [x] **Task 3.3:** Design prominent TV notification popup banner `#kioskAttendanceAlert` and recent attendances card in `resources/views/kiosk/display.php` and `app.css`.
- [x] **Task 3.4:** Implement Web Audio API melodic 2-tone chime (`playChime()`) and Web Speech Synthesis API (`speakAnnouncement()`) in Indonesian in `public/assets/js/kiosk.js`.
- [x] **Task 3.5:** Add sound toggle button with browser autoplay policy auto-unlocking on TV display.
- [x] **Task 3.6:** Add test coverage in `tests/run.php`: 85 tests passed, 0 failed.
