<?php

declare(strict_types=1);

namespace App\Services;

final class GeoService
{
    private const EARTH_RADIUS_METERS = 6371000.0;

    /**
     * Menghitung jarak dua koordinat dalam meter menggunakan rumus Haversine.
     */
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2.0) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLng / 2.0) ** 2);

        $c = 2.0 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));
        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * @return array{ok: bool, distance_m: float, radius_m: float, accuracy_m: float, message: string}
     */
    public function validateLocation(
        float $userLat,
        float $userLng,
        float $accuracyM,
        float $officeLat,
        float $officeLng,
        float $radiusM,
        float $maxAccuracyM
    ): array {
        if (!is_finite($userLat) || $userLat < -90.0 || $userLat > 90.0 || !is_finite($userLng) || $userLng < -180.0 || $userLng > 180.0) {
            return [
                'ok' => false,
                'distance_m' => 0.0,
                'radius_m' => $radiusM,
                'accuracy_m' => $accuracyM,
                'message' => 'Koordinat GPS tidak valid.',
            ];
        }

        if (!is_finite($accuracyM) || $accuracyM <= 0.0) {
            return [
                'ok' => false,
                'distance_m' => 0.0,
                'radius_m' => $radiusM,
                'accuracy_m' => $accuracyM,
                'message' => 'Data akurasi GPS tidak valid. Aktifkan lokasi akurasi tinggi.',
            ];
        }

        if ($accuracyM > $maxAccuracyM) {
            return [
                'ok' => false,
                'distance_m' => 0.0,
                'radius_m' => $radiusM,
                'accuracy_m' => round($accuracyM, 1),
                'message' => sprintf(
                    'Akurasi GPS terlalu rendah (+/- %.0f m, batas maksimal %.0f m). Coba pindah ke area terbuka lalu ulangi.',
                    $accuracyM,
                    $maxAccuracyM
                ),
            ];
        }

        $distance = $this->distanceMeters($userLat, $userLng, $officeLat, $officeLng);
        if ($distance > $radiusM) {
            return [
                'ok' => false,
                'distance_m' => round($distance, 1),
                'radius_m' => $radiusM,
                'accuracy_m' => round($accuracyM, 1),
                'message' => sprintf(
                    'Lokasi Anda berada di luar radius kantor (jarak %.1f m, batas %.0f m).',
                    $distance,
                    $radiusM
                ),
            ];
        }

        return [
            'ok' => true,
            'distance_m' => round($distance, 1),
            'radius_m' => $radiusM,
            'accuracy_m' => round($accuracyM, 1),
            'message' => 'Lokasi berada di dalam radius kantor.',
        ];
    }
}
