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

## PRAR Cycle 4: TV Monitor Centered Layout (Big QR Code & Pure Pop-up Notifications)
- **Timestamp:** 2026-11-23T09:03:00+07:00
- **Perceive:** User provided a screenshot showing the 2-column layout was cramped on TV displays and requested:
  1. Center the QR code and make it much larger for effortless scanning from a distance.
  2. Move instructions, stats, and schedule text to the bottom.
  3. Remove the permanent live attendance list widget from the kiosk screen.
  4. Ensure attendance alerts appear solely as temporary, floating pop-up banners with sound/speech when an employee scans.
- **Reason:**
  - A 2-column layout splits viewport width and forces the QR card to be narrow or overflow vertically.
  - A single, centered layout allows the QR code to dynamically scale via `clamp(260px, 46vh, 440px)` so it fills the screen without vertical scrollbars.
  - Removing the static list eliminates clutter while keeping the real-time polling active in the background for pop-up triggers and voice announcements.
- **Act:**
  - Redesigned `resources/views/kiosk/display.php` with a clean, centered vertical hierarchy.
  - Completely removed the static `.kiosk-recent-card` list from the view.
  - Styled `.kiosk-main`, `.kiosk-qr-card`, `.kiosk-bottom-info`, `.kiosk-chips-row`, and `.kiosk-chip` in `public/assets/css/app.css`.
  - Preserved background activity polling in `public/assets/js/kiosk.js` so pop-ups and speech announcements continue firing seamlessly.
  - Updated `tests/run.php` asserting centered QR card and clean UI.
- **Refine:**
  - Executed test suite with `wsl php tests/run.php`: **86 passed, 0 failed**.
  - Local dev server is running on `http://localhost:8000/kiosk`.

## PRAR Cycle 5: PWA Offline Page Theming & Illustration
- **Timestamp:** 2026-11-23T09:13:00+07:00
- **Perceive:** User requested updating `public/offline.html`: change background color to `#FEFAF3` and add the image `images.jpg`. When server was shut down for offline testing, the image failed to load because external requests fail when disconnected.
- **Reason:**
  - Referencing external paths like `<img src="/images.jpg">` requires either an active server or a prior service worker precache. If the user turns off the server before the new asset is cached, or opens the file directly, the image breaks.
  - The standard offline solution is embedding the image directly as an inline Base64 Data URI (`data:image/jpeg;base64,...`). Since the image is ~43KB (resulting in ~56KB base64), it is lightweight, self-contained, and guaranteed to render 100% offline under all circumstances without any network requests.
- **Act:**
  - Embedded `images.jpg` as inline Base64 data URI directly inside `public/offline.html`.
  - Maintained `#FEFAF3` background, high contrast text, and accessible card styling.
  - Updated `tests/run.php` asserting inline base64 image and color `#FEFAF3`.
- **Refine:**
  - Executed test suite with `wsl php tests/run.php`: **90 passed, 0 failed**.
  - Replaced static JPG with animated `ilustrasi.gif` embedded as an inline Base64 data URI (`data:image/gif;base64,...`) and updated PWA precache. All tests pass.
