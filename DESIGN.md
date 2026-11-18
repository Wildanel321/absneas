# Design Direction: Smart Attendance

Design Read: Operational web application (admin dashboard, employee mobile PWA, fullscreen QR kiosk monitor) for office teams under 30 employees running on Raspberry Pi 3, in a structured utility SaaS language using the navy, white, and violet palette.

Dial: ENERGY 2 / RHYTHM 2 / MOTION 1

## Palette (WCAG AA Verified)

- Core 1 (Navy): `#0F1D3A` (sidebar, kiosk backdrop, primary headings in light mode), `#162647` (elevated navy panels)
- Core 2 (Surface White / Dark Slate): Light canvas `#F4F6FA`, card `#FFFFFF`, border `#D7DFEC`; Dark canvas `#0B1325`, card `#131E36`, border `#273756`
- Accent (Royal Violet): `#5B3FD6` in light mode (6.72:1 on white, 6.72:1 with white text), `#A899FF` in dark mode (6.84:1 on `#131E36`, 6.89:1 with `#0F1D3A` text). Used only for primary actions, active navigation states, and key focus rings.
- Status colors (always paired with text label and icon):
  - On time: `#0E7C5B` on `#ECFDF5` (light), `#6EE7B7` on `#064E3B` (dark)
  - Late / Pending: `#9A5B00` on `#FFFBEB` (light), `#FCD34D` on `#451A03` (dark)
  - Absent / Error: `#B91C1C` on `#FEF2F2` (light), `#FCA5A5` on `#450A0A` (dark)
  - Leave / Sick: `#4338CA` on `#EEF2FF` (light), `#C7D2FE` on `#1E1B4B` (dark)

## Typography

- Primary UI font: Native humanist sans stack (`'Segoe UI', system-ui, -apple-system, Roboto, 'Helvetica Neue', Arial, sans-serif`). Chosen intentionally for zero external CDN calls, instant offline PWA rendering, and low RAM overhead on Raspberry Pi 3.
- Tabular numerals (`font-variant-numeric: tabular-nums`) on clocks, timestamps, GPS distances, and KPI figures so numbers align cleanly and do not jitter during live updates.

## Radii, Elevation, and Motif

- Radius scale: `4px` status tags, `6px` buttons and inputs, `10px` cards and modals. No blanket pill shapes.
- Elevation: flat surfaces separated by crisp 1px borders; subtle shadow reserved for modals, dropdowns, and mobile bottom navigation.
- Identity motif: 2px solid violet top bar on the primary operational card on each screen, paired with live tabular timestamps.
- Motion (MOTION 1): fast 150ms hover/focus transitions, subtle modal entrance, zero infinite pulses, and strict `prefers-reduced-motion: reduce` support.
