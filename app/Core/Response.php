<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    private int $status;
    private string $body;
    /** @var array<string, string> */
    private array $headers;
    private ?string $downloadFilePath = null;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = array_merge([
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(self), geolocation=(self), microphone=()',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https://*.tile.openstreetmap.org; connect-src 'self' https://nominatim.openstreetmap.org; media-src 'self' blob:; manifest-src 'self'; worker-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'",
        ], $headers);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new self($encoded === false ? '{}' : $encoded, $status, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, [
            'Location' => $url,
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function download(string $filePath, string $downloadName, string $mimeType = 'application/octet-stream'): self
    {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $downloadName) ?: 'download.bin';
        $res = new self('', 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $safeName . '"',
            'Content-Length' => (string) (is_file($filePath) ? filesize($filePath) : 0),
            'Cache-Control' => 'no-store',
        ]);
        $res->downloadFilePath = $filePath;
        return $res;
    }

    public static function csv(string $csvContent, string $filename): self
    {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?: 'export.csv';
        return new self($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $safeName . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        if ($this->downloadFilePath !== null && is_file($this->downloadFilePath)) {
            readfile($this->downloadFilePath);
            return;
        }

        echo $this->body;
    }
}
