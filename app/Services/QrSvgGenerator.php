<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Pure PHP ISO/IEC 18004 QR Code (Model 2, Byte Mode, ECC Level M, Versions 1-10) SVG generator.
 * Zero external APIs or dependencies so QR tokens never leave the local Raspberry Pi server.
 */
final class QrSvgGenerator
{
    /**
     * [version => [totalDataCw, ecPerBlock, g1Blocks, g1DataCw, g2Blocks, g2DataCw, alignCoords]]
     *
     * @var array<int, array{0:int,1:int,2:int,3:int,4:int,5:int,6:list<int>}>
     */
    private const VERSIONS_M = [
        1 => [16, 10, 1, 16, 0, 0, []],
        2 => [28, 16, 1, 28, 0, 0, [6, 18]],
        3 => [44, 26, 1, 44, 0, 0, [6, 22]],
        4 => [64, 18, 2, 32, 0, 0, [6, 26]],
        5 => [86, 24, 2, 43, 0, 0, [6, 30]],
        6 => [108, 16, 4, 27, 0, 0, [6, 34]],
        7 => [124, 18, 4, 31, 0, 0, [6, 22, 38]],
        8 => [154, 22, 2, 38, 2, 39, [6, 24, 42]],
        9 => [182, 22, 3, 36, 2, 37, [6, 26, 46]],
        10 => [216, 26, 4, 43, 1, 44, [6, 28, 50]],
    ];

    /**
     * Returns a 2D boolean matrix (true = dark module, false = light module).
     *
     * @return list<list<bool>>
     */
    public function encodeMatrix(string $text): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $len = count($bytes);

        $version = $this->chooseVersion($len);
        $spec = self::VERSIONS_M[$version];
        $size = 17 + (4 * $version);

        $dataCodewords = $this->buildDataCodewords($bytes, $version, $spec[0]);
        $finalCodewords = $this->interleaveBlocks($dataCodewords, $spec);

        $modules = array_fill(0, $size, array_fill(0, $size, false));
        $isFunction = array_fill(0, $size, array_fill(0, $size, false));

        $this->drawFunctionPatterns($modules, $isFunction, $version, $size, $spec[6]);
        $this->drawCodewords($modules, $isFunction, $finalCodewords, $size);

        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestModules = $modules;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = $modules;
            $this->applyMask($candidate, $isFunction, $mask, $size);
            $this->drawFormatBits($candidate, $mask, $size);
            $penalty = $this->calculatePenalty($candidate, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
                $bestModules = $candidate;
            }
        }

        // Pastikan format bits final sesuai mask terbaik
        $this->drawFormatBits($bestModules, $bestMask, $size);
        return $bestModules;
    }

    public function renderSvg(string $text, int $quietZone = 4, string $darkColor = '#0F1D3A', string $lightColor = '#FFFFFF'): string
    {
        $matrix = $this->encodeMatrix($text);
        $size = count($matrix);
        $total = $size + ($quietZone * 2);

        $paths = [];
        for ($y = 0; $y < $size; $y++) {
            $x = 0;
            while ($x < $size) {
                if (!$matrix[$y][$x]) {
                    $x++;
                    continue;
                }
                $runStart = $x;
                while ($x < $size && $matrix[$y][$x]) {
                    $x++;
                }
                $runLen = $x - $runStart;
                $px = $runStart + $quietZone;
                $py = $y + $quietZone;
                $paths[] = "M{$px},{$py}h{$runLen}v1h-{$runLen}z";
            }
        }

        $d = implode('', $paths);
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="320" height="320" role="img" aria-label="QR Code Absensi" shape-rendering="crispEdges"><rect width="100%%" height="100%%" fill="%s"/><path d="%s" fill="%s"/></svg>',
            $total,
            $total,
            htmlspecialchars($lightColor, ENT_QUOTES, 'UTF-8'),
            $d,
            htmlspecialchars($darkColor, ENT_QUOTES, 'UTF-8')
        );
    }

    private function chooseVersion(int $byteLen): int
    {
        foreach (self::VERSIONS_M as $v => $spec) {
            $countBits = $v <= 9 ? 8 : 16;
            $capacityBits = $spec[0] * 8;
            $neededBits = 4 + $countBits + ($byteLen * 8);
            if ($neededBits <= $capacityBits) {
                return $v;
            }
        }
        throw new InvalidArgumentException('Data terlalu panjang untuk QR Version 1-10.');
    }

    /**
     * @param list<int> $bytes
     * @return list<int>
     */
    private function buildDataCodewords(array $bytes, int $version, int $totalDataCw): array
    {
        $bits = [];
        // Mode indicator: Byte mode = 0100
        $this->appendBits($bits, 0b0100, 4);
        // Character count indicator
        $countBits = $version <= 9 ? 8 : 16;
        $this->appendBits($bits, count($bytes), $countBits);

        foreach ($bytes as $b) {
            $this->appendBits($bits, $b, 8);
        }

        $capacityBits = $totalDataCw * 8;
        $terminator = min(4, $capacityBits - count($bits));
        $this->appendBits($bits, 0, $terminator);

        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $codewords = [];
        $bitCount = count($bits);
        for ($i = 0; $i < $bitCount; $i += 8) {
            $val = 0;
            for ($j = 0; $j < 8; $j++) {
                $val = ($val << 1) | $bits[$i + $j];
            }
            $codewords[] = $val;
        }

        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($codewords) < $totalDataCw) {
            $codewords[] = $padBytes[$padIdx];
            $padIdx = 1 - $padIdx;
        }

        return $codewords;
    }

    /**
     * @param list<int> $bits
     */
    private function appendBits(array &$bits, int $val, int $len): void
    {
        for ($i = $len - 1; $i >= 0; $i--) {
            $bits[] = ($val >> $i) & 1;
        }
    }

    /**
     * @param list<int> $dataCodewords
     * @param array{0:int,1:int,2:int,3:int,4:int,5:int,6:list<int>} $spec
     * @return list<int>
     */
    private function interleaveBlocks(array $dataCodewords, array $spec): array
    {
        [, $ecPerBlock, $g1Blocks, $g1DataCw, $g2Blocks, $g2DataCw] = $spec;

        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;
        $divisor = $this->rsComputeDivisor($ecPerBlock);

        for ($i = 0; $i < $g1Blocks; $i++) {
            $block = array_slice($dataCodewords, $offset, $g1DataCw);
            $offset += $g1DataCw;
            $dataBlocks[] = $block;
            $ecBlocks[] = $this->rsComputeRemainder($block, $divisor);
        }

        for ($i = 0; $i < $g2Blocks; $i++) {
            $block = array_slice($dataCodewords, $offset, $g2DataCw);
            $offset += $g2DataCw;
            $dataBlocks[] = $block;
            $ecBlocks[] = $this->rsComputeRemainder($block, $divisor);
        }

        $result = [];
        $maxDataLen = max($g1DataCw, $g2DataCw);
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $result[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($ecBlocks as $block) {
                $result[] = $block[$i];
            }
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    private function rsComputeDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;

        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = $this->gfMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = $this->gfMultiply($root, 0x02);
        }

        return $result;
    }

    /**
     * @param list<int> $data
     * @param list<int> $divisor
     * @return list<int>
     */
    private function rsComputeRemainder(array $data, array $divisor): array
    {
        $deg = count($divisor);
        $result = array_fill(0, $deg, 0);

        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            for ($i = 0; $i < $deg; $i++) {
                $result[$i] ^= $this->gfMultiply($divisor[$i], $factor);
            }
        }

        return $result;
    }

    private function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z & 0xFF;
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $isFunction
     * @param list<int> $alignCoords
     */
    private function drawFunctionPatterns(array &$modules, array &$isFunction, int $version, int $size, array $alignCoords): void
    {
        // Timing patterns
        for ($i = 0; $i < $size; $i++) {
            $this->setFunctionModule($modules, $isFunction, 6, $i, $i % 2 === 0);
            $this->setFunctionModule($modules, $isFunction, $i, 6, $i % 2 === 0);
        }

        // Finder patterns + separators
        $this->drawFinderPattern($modules, $isFunction, 3, 3, $size);
        $this->drawFinderPattern($modules, $isFunction, $size - 4, 3, $size);
        $this->drawFinderPattern($modules, $isFunction, 3, $size - 4, $size);

        // Alignment patterns
        $numAlign = count($alignCoords);
        for ($i = 0; $i < $numAlign; $i++) {
            for ($j = 0; $j < $numAlign; $j++) {
                $isCorner = ($i === 0 && $j === 0)
                    || ($i === 0 && $j === $numAlign - 1)
                    || ($i === $numAlign - 1 && $j === 0);
                if (!$isCorner) {
                    $this->drawAlignmentPattern($modules, $isFunction, $alignCoords[$i], $alignCoords[$j]);
                }
            }
        }

        // Reserve format info areas (filled after masking)
        $this->drawFormatBits($modules, 0, $size, $isFunction);

        // Version info (version >= 7)
        if ($version >= 7) {
            $this->drawVersionBits($modules, $isFunction, $version, $size);
        }
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $isFunction
     */
    private function setFunctionModule(array &$modules, array &$isFunction, int $x, int $y, bool $isDark): void
    {
        $modules[$y][$x] = $isDark;
        $isFunction[$y][$x] = true;
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $isFunction
     */
    private function drawFinderPattern(array &$modules, array &$isFunction, int $cx, int $cy, int $size): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $xx = $cx + $dx;
                $yy = $cy + $dy;
                if ($xx >= 0 && $xx < $size && $yy >= 0 && $yy < $size) {
                    $dist = max(abs($dx), abs($dy));
                    $this->setFunctionModule($modules, $isFunction, $xx, $yy, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $isFunction
     */
    private function drawAlignmentPattern(array &$modules, array &$isFunction, int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $this->setFunctionModule($modules, $isFunction, $cx + $dx, $cy + $dy, $dist !== 1);
            }
        }
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>>|null $isFunction
     */
    private function drawFormatBits(array &$modules, int $mask, int $size, ?array &$isFunction = null): void
    {
        // EC Level M = 0b00
        $data = (0b00 << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        $set = static function (int $x, int $y, bool $dark) use (&$modules, &$isFunction): void {
            $modules[$y][$x] = $dark;
            if ($isFunction !== null) {
                $isFunction[$y][$x] = true;
            }
        };

        for ($i = 0; $i <= 5; $i++) {
            $set(8, $i, (($bits >> $i) & 1) !== 0);
        }
        $set(8, 7, (($bits >> 6) & 1) !== 0);
        $set(8, 8, (($bits >> 7) & 1) !== 0);
        $set(7, 8, (($bits >> 8) & 1) !== 0);
        for ($i = 9; $i < 15; $i++) {
            $set(14 - $i, 8, (($bits >> $i) & 1) !== 0);
        }

        for ($i = 0; $i < 8; $i++) {
            $set($size - 1 - $i, 8, (($bits >> $i) & 1) !== 0);
        }
        for ($i = 8; $i < 15; $i++) {
            $set(8, $size - 15 + $i, (($bits >> $i) & 1) !== 0);
        }
        // Dark module
        $set(8, $size - 8, true);
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $isFunction
     */
    private function drawVersionBits(array &$modules, array &$isFunction, int $version, int $size): void
    {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($version << 12) | $rem;

        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) !== 0;
            $a = $size - 11 + ($i % 3);
            $b = intdiv($i, 3);
            $this->setFunctionModule($modules, $isFunction, $a, $b, $dark);
            $this->setFunctionModule($modules, $isFunction, $b, $a, $dark);
        }
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $isFunction
     * @param list<int> $data
     */
    private function drawCodewords(array &$modules, array $isFunction, array $data, int $size): void
    {
        $bitIdx = 0;
        $totalBits = count($data) * 8;

        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? ($size - 1 - $vert) : $vert;
                    if (!$isFunction[$y][$x] && $bitIdx < $totalBits) {
                        $byte = $data[$bitIdx >> 3];
                        $modules[$y][$x] = (($byte >> (7 - ($bitIdx & 7))) & 1) !== 0;
                        $bitIdx++;
                    }
                }
            }
        }
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $isFunction
     */
    private function applyMask(array &$modules, array $isFunction, int $mask, int $size): void
    {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => (($x * $y) % 2) + (($x * $y) % 3) === 0,
                    6 => ((($x * $y) % 2) + (($x * $y) % 3)) % 2 === 0,
                    7 => ((($x + $y) % 2) + (($x * $y) % 3)) % 2 === 0,
                    default => false,
                };
                if ($invert) {
                    $modules[$y][$x] = !$modules[$y][$x];
                }
            }
        }
    }

    /**
     * @param list<list<bool>> $m
     */
    private function calculatePenalty(array $m, int $size): int
    {
        $penalty = 0;

        // Adjacent modules in row/col
        for ($y = 0; $y < $size; $y++) {
            $runColor = $m[$y][0];
            $runLen = 1;
            for ($x = 1; $x < $size; $x++) {
                if ($m[$y][$x] === $runColor) {
                    $runLen++;
                    if ($runLen === 5) {
                        $penalty += 3;
                    } elseif ($runLen > 5) {
                        $penalty += 1;
                    }
                } else {
                    $runColor = $m[$y][$x];
                    $runLen = 1;
                }
            }
        }
        for ($x = 0; $x < $size; $x++) {
            $runColor = $m[0][$x];
            $runLen = 1;
            for ($y = 1; $y < $size; $y++) {
                if ($m[$y][$x] === $runColor) {
                    $runLen++;
                    if ($runLen === 5) {
                        $penalty += 3;
                    } elseif ($runLen > 5) {
                        $penalty += 1;
                    }
                } else {
                    $runColor = $m[$y][$x];
                    $runLen = 1;
                }
            }
        }

        // 2x2 blocks
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                    $penalty += 3;
                }
            }
        }

        // Balance of dark modules
        $dark = 0;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($m[$y][$x]) {
                    $dark++;
                }
            }
        }
        $total = $size * $size;
        $k = (int) floor(abs($dark * 20 - $total * 10) / $total);
        $penalty += $k * 10;

        return $penalty;
    }
}
