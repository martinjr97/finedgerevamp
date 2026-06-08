<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class DatabaseBackupService
{
    /**
     * Create a database backup archive.
     *
     * @return array{filename: string, path: string, size_bytes: int, created_at: Carbon}
     */
    public function createBackup(): array
    {
        $connectionName = (string) config('database.default');
        $connection = (array) config("database.connections.{$connectionName}");
        $driver = (string) ($connection['driver'] ?? '');

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Backup module currently supports MySQL/MariaDB connections only.');
        }

        $database = (string) ($connection['database'] ?? '');
        if ($database === '') {
            throw new RuntimeException('Database name is not configured.');
        }

        $host = $this->normalizeConnectionValue($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');
        $socket = $this->normalizeConnectionValue($connection['unix_socket'] ?? '');

        $timestamp = now()->format('Ymd_His');
        $databaseSlug = preg_replace('/[^A-Za-z0-9_-]/', '_', $database) ?: 'database';
        $baseFilename = "backup_{$databaseSlug}_{$timestamp}";
        $directory = $this->backupDirectory();
        File::ensureDirectoryExists($directory);

        $sqlPath = $directory.DIRECTORY_SEPARATOR."{$baseFilename}.sql";
        $zipPath = $directory.DIRECTORY_SEPARATOR."{$baseFilename}.zip";

        $dumpBinary = (string) env('DB_DUMP_BINARY', 'mysqldump');
        $command = [
            $dumpBinary,
            "--host={$host}",
            "--port={$port}",
            "--user={$username}",
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--routines',
            '--events',
            "--result-file={$sqlPath}",
            $database,
        ];

        if ($socket !== '') {
            $command[] = "--socket={$socket}";
        }

        $process = new Process($command, null, $password !== '' ? ['MYSQL_PWD' => $password] : null);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            if (File::exists($sqlPath)) {
                File::delete($sqlPath);
            }

            $error = trim($process->getErrorOutput()) ?: 'Unknown dump error';
            throw new RuntimeException("Database dump failed: {$error}");
        }

        if (!File::exists($sqlPath) || File::size($sqlPath) <= 0) {
            throw new RuntimeException('Database dump was created but is empty.');
        }

        if (!class_exists(ZipArchive::class)) {
            File::delete($sqlPath);
            throw new RuntimeException('ZipArchive extension is required to package backups.');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            File::delete($sqlPath);
            throw new RuntimeException('Failed to create backup zip archive.');
        }

        if (!$zip->addFile($sqlPath, basename($sqlPath))) {
            $zip->close();
            File::delete($sqlPath);
            if (File::exists($zipPath)) {
                File::delete($zipPath);
            }
            throw new RuntimeException('Failed to add SQL dump to zip archive.');
        }

        $zip->close();

        File::delete($sqlPath);

        if (!File::exists($zipPath)) {
            throw new RuntimeException('Backup zip archive was not created.');
        }

        return [
            'filename' => basename($zipPath),
            'path' => $zipPath,
            'size_bytes' => (int) File::size($zipPath),
            'created_at' => Carbon::createFromTimestamp(File::lastModified($zipPath)),
        ];
    }

    /**
     * List backup zip files.
     *
     * @return Collection<int, array{filename: string, size_bytes: int, size_human: string, created_at: Carbon}>
     */
    public function listBackups(): Collection
    {
        $directory = $this->backupDirectory();
        if (!File::exists($directory)) {
            return collect();
        }

        return collect(File::files($directory))
            ->filter(fn (\SplFileInfo $file) => strtolower($file->getExtension()) === 'zip')
            ->sortByDesc(fn (\SplFileInfo $file) => $file->getMTime())
            ->values()
            ->map(function (\SplFileInfo $file): array {
                $sizeBytes = (int) $file->getSize();

                return [
                    'filename' => $file->getFilename(),
                    'size_bytes' => $sizeBytes,
                    'size_human' => $this->formatBytes($sizeBytes),
                    'created_at' => Carbon::createFromTimestamp($file->getMTime()),
                ];
            });
    }

    /**
     * Resolve a safe absolute backup path from filename.
     */
    public function resolveBackupPath(string $filename): string
    {
        $safeFilename = basename($filename);
        if ($safeFilename !== $filename || !str_ends_with(strtolower($safeFilename), '.zip')) {
            throw new RuntimeException('Invalid backup filename.');
        }

        $path = $this->backupDirectory().DIRECTORY_SEPARATOR.$safeFilename;
        if (!File::exists($path)) {
            throw new RuntimeException('Backup file not found.');
        }

        return $path;
    }

    public function deleteBackup(string $filename): void
    {
        $path = $this->resolveBackupPath($filename);

        if (!File::delete($path)) {
            throw new RuntimeException('Failed to delete backup file.');
        }
    }

    private function backupDirectory(): string
    {
        return storage_path('app/backups');
    }

    /**
     * @param mixed $value
     */
    private function normalizeConnectionValue($value): string
    {
        if (is_array($value)) {
            return (string) (reset($value) ?: '');
        }

        return (string) $value;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2).' MB';
        }

        return number_format($bytes / (1024 * 1024 * 1024), 2).' GB';
    }
}
