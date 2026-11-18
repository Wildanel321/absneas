import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const jsQR = require('../public/assets/js/vendor/jsqr.js');

const samples = [
  'SAT1|checkin|1795000000|0123456789abcdef0123456789abcdef|abcdef0123456789abcdef01',
  'SAT1|checkout|1795036000|fedcba9876543210fedcba9876543210|11223344556677889900aabb',
  'SHORT-V1',
  'LONGER-PAYLOAD-' + 'A'.repeat(140),
];

for (const token of samples) {
  const phpCode = `require 'app/Services/QrSvgGenerator.php'; $g = new App\\Services\\QrSvgGenerator(); echo json_encode($g->encodeMatrix(${JSON.stringify(token)}));`;
  const raw = execFileSync('php', ['-r', phpCode], { encoding: 'utf8' });
  const m = JSON.parse(raw);
  const q = 4;
  const scale = 4;
  const size = (m.length + 2 * q) * scale;
  const rgba = new Uint8ClampedArray(size * size * 4);
  rgba.fill(255);
  for (let y = 0; y < m.length; y++) {
    for (let x = 0; x < m.length; x++) {
      if (m[y][x]) {
        for (let dy = 0; dy < scale; dy++) {
          for (let dx = 0; dx < scale; dx++) {
            const px = (x + q) * scale + dx;
            const py = (y + q) * scale + dy;
            const i = (py * size + px) * 4;
            rgba[i] = 15;
            rgba[i + 1] = 29;
            rgba[i + 2] = 58;
            rgba[i + 3] = 255;
          }
        }
      }
    }
  }

  const decoded = jsQR(rgba, size, size);
  if (!decoded || decoded.data !== token) {
    console.error('QR DECODE FAILED for length', token.length, 'got:', decoded ? decoded.data : null);
    process.exit(1);
  }
  console.log(`PASS [matrix ${m.length}x${m.length}, len=${token.length}]: decoded identically`);
}
