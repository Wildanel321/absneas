<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private string $method;
    private string $path;
    /** @var array<string, mixed> */
    private array $query;
    /** @var array<string, mixed> */
    private array $post;
    /** @var array<string, mixed>|null */
    private ?array $jsonBody = null;
    /** @var array<string, mixed> */
    private array $server;
    /** @var array<string, mixed> */
    private array $files;
    private bool $trustProxy;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     */
    public function __construct(
        string $method,
        string $path,
        array $query = [],
        array $post = [],
        array $server = [],
        array $files = [],
        ?string $rawBody = null,
        bool $trustProxy = true
    ) {
        $this->method = strtoupper($method);
        $this->path = '/' . trim($path, '/');
        if ($this->path !== '/') {
            $this->path = rtrim($this->path, '/');
        }
        $this->query = $query;
        $this->post = $post;
        $this->server = $server;
        $this->files = $files;
        $this->trustProxy = $trustProxy;

        $contentType = strtolower((string) ($server['CONTENT_TYPE'] ?? $server['HTTP_CONTENT_TYPE'] ?? ''));
        if ($rawBody !== null && $rawBody !== '' && str_contains($contentType, 'application/json')) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $this->jsonBody = $decoded;
            }
        }
    }

    public static function capture(bool $trustProxy = true): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        $rawBody = file_get_contents('php://input');

        return new self(
            $method,
            $path,
            $_GET,
            $_POST,
            $_SERVER,
            $_FILES,
            $rawBody === false ? null : $rawBody,
            $trustProxy
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function allQuery(): array
    {
        return $this->query;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if ($this->jsonBody !== null && array_key_exists($key, $this->jsonBody)) {
            return $this->jsonBody[$key];
        }
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        }
        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function allInput(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }
        return $this->post;
    }

    public function file(string $key): ?array
    {
        $f = $this->files[$key] ?? null;
        return is_array($f) ? $f : null;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$key])) {
            return (string) $this->server[$key];
        }
        $alt = strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$alt])) {
            return (string) $this->server[$alt];
        }
        return $default;
    }

    public function expectsJson(): bool
    {
        if (str_starts_with($this->path, '/api/')) {
            return true;
        }
        $accept = strtolower((string) $this->header('Accept', ''));
        $xhr = strtolower((string) $this->header('X-Requested-With', ''));
        return str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest';
    }

    public function isHttps(): bool
    {
        if (!empty($this->server['HTTPS']) && strtolower((string) $this->server['HTTPS']) !== 'off') {
            return true;
        }
        if ($this->trustProxy) {
            $proto = strtolower((string) $this->header('X-Forwarded-Proto', ''));
            if ($proto === 'https') {
                return true;
            }
            $cfVisitor = (string) $this->header('CF-Visitor', '');
            if ($cfVisitor !== '' && str_contains($cfVisitor, '"https"')) {
                return true;
            }
        }
        return false;
    }

    public function ip(): string
    {
        if ($this->trustProxy) {
            $cfIp = trim((string) $this->header('CF-Connecting-IP', ''));
            if ($cfIp !== '' && filter_var($cfIp, FILTER_VALIDATE_IP)) {
                return $cfIp;
            }
            $xff = trim((string) $this->header('X-Forwarded-For', ''));
            if ($xff !== '') {
                $first = trim(explode(',', $xff)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }

        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '127.0.0.1');
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) $this->header('User-Agent', ''), 0, 255);
    }

    public function csrfToken(): ?string
    {
        $headerToken = $this->header('X-CSRF-Token');
        if ($headerToken !== null && $headerToken !== '') {
            return $headerToken;
        }
        $inputToken = $this->input('_csrf');
        return is_string($inputToken) ? $inputToken : null;
    }
}
