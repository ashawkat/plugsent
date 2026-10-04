<?php

namespace App\Support;

use Closure;
use RuntimeException;
use Throwable;

/**
 * Thin crontab accessor so the app can install its own scheduler entry.
 * Reader/writer are injectable so tests never touch the real crontab.
 */
class Crontab
{
    public function __construct(
        private ?Closure $reader = null,
        private ?Closure $writer = null,
    ) {
        $this->reader ??= fn (): string => (string) (shell_exec('crontab -l 2>/dev/null') ?? '');
        $this->writer ??= function (string $content): void {
            $process = proc_open('crontab -', [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);

            if (! is_resource($process)) {
                throw new RuntimeException('Unable to open crontab for writing.');
            }

            fwrite($pipes[0], $content);
            fclose($pipes[0]);
            $exit = proc_close($process);

            if ($exit !== 0) {
                throw new RuntimeException("crontab write failed with exit code {$exit}.");
            }
        };
    }

    /**
     * Ensure the app's `schedule:run` entry exists; true when the crontab
     * was changed, false when the entry was already present.
     */
    public function ensureScheduleEntry(string $basePath): bool
    {
        $crontab = ($this->reader)();

        if ($this->hasScheduleEntry($crontab, $basePath)) {
            return false;
        }

        $entry = sprintf('* * * * * cd %s && php artisan schedule:run >> /dev/null 2>&1', escapeshellarg($basePath));

        ($this->writer)(rtrim($crontab, "\n")."\n".$entry."\n");

        return true;
    }

    public function hasScheduleEntry(string $crontab, string $basePath): bool
    {
        foreach (explode("\n", $crontab) as $line) {
            if (str_contains($line, 'artisan schedule:run') && str_contains($line, $basePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether PHP is allowed to touch the crontab at all (some hosts
     * disable exec/proc_open) — used to fail loudly instead of silently.
     */
    public function isWritable(): bool
    {
        try {
            ($this->reader)();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
