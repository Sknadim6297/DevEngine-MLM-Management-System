<?php

namespace App\Services;

use RuntimeException;

/**
 * Durable JSON checkpoint for the accelerated financial cycle. Writes are atomic
 * (temp file + rename) so an interrupted process never leaves a torn checkpoint.
 */
class FinancialCycleCheckpoint
{
    public function __construct(private readonly string $path)
    {
    }

    public function load(): ?array
    {
        if (! is_file($this->path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->path), true);
        if (! is_array($data) || ! isset($data['next_business_date'])) {
            throw new RuntimeException('Financial cycle checkpoint is unreadable: ' . $this->path);
        }

        return $data;
    }

    public function save(array $data): void
    {
        $directory = dirname($this->path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create checkpoint directory: ' . $directory);
        }

        $data['updated_at'] = now('Asia/Kolkata')->toIso8601String();
        $temporary = $this->path . '.' . getmypid() . '.tmp';
        if (file_put_contents($temporary, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new RuntimeException('Cannot write financial cycle checkpoint.');
        }

        if (! rename($temporary, $this->path)) {
            @unlink($temporary);
            throw new RuntimeException('Cannot replace financial cycle checkpoint.');
        }
    }
}
