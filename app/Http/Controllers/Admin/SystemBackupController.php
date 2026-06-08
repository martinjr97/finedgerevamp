<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class SystemBackupController extends Controller
{
    public function downloadUploadsBackup(): BinaryFileResponse|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('backups.download'), 403);

        $sourcePath = $this->resolveUploadsSourcePath();
        if ($sourcePath === null) {
            return redirect()
                ->route('admin.backups.index')
                ->with('error', 'Uploads directory was not found. Expected storage/app/public or public/uploads.');
        }

        if (!class_exists(ZipArchive::class)) {
            abort(500, 'ZipArchive extension is required to package uploads backup.');
        }

        $backupDirectory = storage_path('app/backups');
        File::ensureDirectoryExists($backupDirectory);

        $zipFilename = 'uploads_backup_'.now()->format('Ymd_His').'.zip';
        $zipPath = $backupDirectory.DIRECTORY_SEPARATOR.$zipFilename;

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            abort(500, 'Failed to create uploads backup archive.');
        }

        $sourceLength = strlen($sourcePath);
        foreach (File::allFiles($sourcePath) as $file) {
            $realPath = $file->getRealPath();
            if ($realPath === false || str_starts_with($realPath, $backupDirectory.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $relativePath = ltrim(substr($realPath, $sourceLength), DIRECTORY_SEPARATOR);
            $relativePath = str_replace('\\', '/', $relativePath);

            $zip->addFile($realPath, $relativePath);
        }

        $zip->close();

        if (!File::exists($zipPath)) {
            abort(500, 'Uploads backup archive was not created.');
        }

        return response()
            ->download($zipPath, $zipFilename, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    private function resolveUploadsSourcePath(): ?string
    {
        $configuredPublicRoot = config('filesystems.disks.public.root');
        $candidates = collect([
            is_string($configuredPublicRoot) ? $configuredPublicRoot : null,
            storage_path('app/public'),
            public_path('uploads'),
        ])
            ->filter(fn (?string $path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): string => rtrim($path, DIRECTORY_SEPARATOR))
            ->unique()
            ->values();

        foreach ($candidates as $candidate) {
            if (File::isDirectory($candidate)) {
                $realPath = realpath($candidate);

                return $realPath !== false ? $realPath : $candidate;
            }
        }

        return null;
    }
}
