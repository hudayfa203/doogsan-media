<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BackupRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Database backups to storage/app/backups with retention. For MySQL/MariaDB
 * this uses mysqldump (must be installed on the server); SQLite is copied.
 * Production should ALSO ship backups off-server (see docs/DEPLOYMENT.md).
 */
class BackupService
{
    public function dir(): string
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir, 0750);

        return $dir;
    }

    public function run(): BackupRun
    {
        $driver = DB::connection()->getDriverName();
        $file = $this->dir().'/doogsan-'.now()->format('Ymd-His');

        try {
            if ($driver === 'sqlite') {
                $file .= '.sqlite';
                copy(DB::connection()->getDatabaseName(), $file);
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $file .= '.sql.gz';
                $c = config('database.connections.'.config('database.default'));
                $cmd = sprintf('mysqldump --single-transaction --quick --routines -h %s -P %s -u %s %s | gzip > %s',
                    escapeshellarg($c['host']), escapeshellarg((string) $c['port']), escapeshellarg($c['username']),
                    escapeshellarg($c['database']), escapeshellarg($file));
                $p = Process::fromShellCommandline($cmd, null, ['MYSQL_PWD' => (string) $c['password']], null, 600);
                $p->run();
                if (! $p->isSuccessful() || filesize($file) < 100) {
                    throw new \RuntimeException(trim($p->getErrorOutput()) ?: 'mysqldump produced no output');
                }
            } else {
                throw new \RuntimeException("Driver {$driver} not supported by built-in backups");
            }

            $run = BackupRun::create(['status' => 'success', 'path' => basename($file), 'size_bytes' => filesize($file), 'message' => $driver]);
            $this->prune();
        } catch (\Throwable $e) {
            @unlink($file);
            $run = BackupRun::create(['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 500)]);
        }

        AuditLog::record('backup.'.$run->status, 'Database backup '.$run->status, $run);

        return $run;
    }

    public function prune(): void
    {
        $keep = (int) setting('backup_retention_days', 14);
        foreach (File::files($this->dir()) as $f) {
            if ($f->getMTime() < now()->subDays($keep)->getTimestamp()) {
                @unlink($f->getPathname());
            }
        }
    }

    public function files(): array
    {
        return collect(File::files($this->dir()))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'time' => $f->getMTime()])
            ->values()->all();
    }
}
