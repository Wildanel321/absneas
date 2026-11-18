<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private string $logDir;

    public function __construct(string $logDir)
    {
        $this->logDir = $logDir;
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $file = $this->logDir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        $entry = sprintf(
            "[%s] [%s] %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );

        @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }
}
