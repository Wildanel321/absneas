<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function str(mixed $value, int $maxLength = 255): string
    {
        $text = trim((string) ($value ?? ''));
        if (mb_strlen($text, 'UTF-8') > $maxLength) {
            $text = mb_substr($text, 0, $maxLength, 'UTF-8');
        }
        return $text;
    }

    public static function isUsername(string $username): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $username);
    }

    public static function isEmail(?string $email): bool
    {
        if ($email === null || $email === '') {
            return true;
        }
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function isDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        return checkdate($m, $d, $y);
    }

    public static function isTime(string $time): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time);
    }

    public static function isLatitude(mixed $lat): bool
    {
        if (!is_numeric($lat)) {
            return false;
        }
        $v = (float) $lat;
        return is_finite($v) && $v >= -90.0 && $v <= 90.0;
    }

    public static function isLongitude(mixed $lng): bool
    {
        if (!is_numeric($lng)) {
            return false;
        }
        $v = (float) $lng;
        return is_finite($v) && $v >= -180.0 && $v <= 180.0;
    }
}
