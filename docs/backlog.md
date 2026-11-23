# Project Backlog & Implementation Tasks

## Sprint 1: Interactive Office Geofencing Map & Mobile UI Hygiene

- [x] **Task 1.1:** Download and vendor Leaflet v1.9.4 assets locally (`leaflet.js`, `leaflet.css`, marker images) using `aria2c`.
- [x] **Task 1.2:** Update Content-Security-Policy in `app/Core/Response.php` to whitelist OpenStreetMap tile domains (`https://*.tile.openstreetmap.org`).
- [x] **Task 1.3:** Remove redundant "Install Aplikasi" button from employee PWA layout (`resources/views/layouts/employee.php`) to reduce mobile header clutter.
- [x] **Task 1.4:** Integrate Leaflet map container and Google Maps paste parser in `resources/views/admin/settings.php`.
- [x] **Task 1.5:** Implement two-way coordinate binding, radius circle visualization, and GPS autofill sync in `public/assets/js/app.js`.
- [x] **Task 1.6:** Apply `antislop-layoutmobile` audit: ensure 44x44px minimum tap targets, no horizontal overflow, responsive form wrapping.
- [x] **Task 1.7:** Execute test suite (`wsl php tests/run.php`) to ensure 100% pass rate (65 tests passed).
