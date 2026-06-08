<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $backupService
    ) {
    }

    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('backups.view'), 403);

        $backups = $this->backupService->listBackups();

        return view('admin.backups.index', [
            'backups' => $backups,
        ]);
    }

    public function store(): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('backups.create'), 403);

        try {
            $backup = $this->backupService->createBackup();

            return redirect()
                ->route('admin.backups.index')
                ->with('status', "Backup generated successfully: {$backup['filename']}");
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.backups.index')
                ->with('error', 'Backup failed: '.$e->getMessage());
        }
    }

    public function download(string $filename): BinaryFileResponse
    {
        abort_unless(auth('admin')->user()?->can('backups.download'), 403);

        try {
            $path = $this->backupService->resolveBackupPath($filename);
        } catch (RuntimeException $e) {
            abort(404, $e->getMessage());
        }

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function destroy(string $filename): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('backups.download'), 403);

        try {
            $this->backupService->deleteBackup($filename);

            return redirect()
                ->route('admin.backups.index')
                ->with('status', "Backup deleted successfully: {$filename}");
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.backups.index')
                ->with('error', 'Backup delete failed: '.$e->getMessage());
        }
    }
}
