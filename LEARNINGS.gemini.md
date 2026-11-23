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
