<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Company;
use App\Services\DatabaseBackupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use ZipArchive;

class AdminBackupModuleTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithPermissions(array $permissions = []): Admin
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Backup Co '.$suffix,
            'slug' => 'backup-co-'.$suffix,
            'code' => 'BC-'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Backup',
            'last_name' => 'Admin',
            'email' => 'backup-admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
            'must_change_password' => false,
        ]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }
        if (!empty($permissions)) {
            $admin->givePermissionTo($permissions);
        }

        return $admin;
    }

    private function bindFakeBackupService(array $backups = [], ?string $resolvedPath = null): DatabaseBackupService
    {
        $service = new class($backups, $resolvedPath) extends DatabaseBackupService
        {
            /**
             * @var array<int, array{filename: string, size_bytes: int, size_human: string, created_at: Carbon}>
             */
            public array $listData;

            public ?string $resolvedPath;

            public bool $createCalled = false;

            public ?string $resolvedFilename = null;

            public ?string $deletedFilename = null;

            public function __construct(array $listData, ?string $resolvedPath)
            {
                $this->listData = $listData;
                $this->resolvedPath = $resolvedPath;
            }

            public function createBackup(): array
            {
                $this->createCalled = true;

                return [
                    'filename' => 'backup_demo_20260312_010203.zip',
                    'path' => storage_path('app/backups/backup_demo_20260312_010203.zip'),
                    'size_bytes' => 1024,
                    'created_at' => Carbon::parse('2026-03-12 01:02:03'),
                ];
            }

            public function listBackups(): Collection
            {
                return collect($this->listData);
            }

            public function resolveBackupPath(string $filename): string
            {
                $this->resolvedFilename = $filename;

                if ($this->resolvedPath === null) {
                    throw new \RuntimeException('Backup file not found.');
                }

                return $this->resolvedPath;
            }

            public function deleteBackup(string $filename): void
            {
                $this->deletedFilename = $filename;

                if ($this->resolvedPath === null) {
                    throw new \RuntimeException('Backup file not found.');
                }
            }
        };

        $this->app->instance(DatabaseBackupService::class, $service);

        return $service;
    }

    public function test_backup_index_requires_view_permission(): void
    {
        $admin = $this->makeAdminWithPermissions();
        $this->bindFakeBackupService();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.backups.index'));

        $response->assertForbidden();
    }

    public function test_backup_index_shows_archives_for_authorized_admin(): void
    {
        $admin = $this->makeAdminWithPermissions(['backups.view', 'backups.create', 'backups.download']);
        $this->bindFakeBackupService([
            [
                'filename' => 'backup_demo_20260312_120000.zip',
                'size_bytes' => 2048,
                'size_human' => '2.00 KB',
                'created_at' => Carbon::parse('2026-03-12 12:00:00'),
            ],
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.backups.index'));

        $response->assertOk();
        $response->assertSee('Database Backups');
        $response->assertSee('Generate Backup');
        $response->assertSee('backup_demo_20260312_120000.zip');
        $response->assertSee('Download ZIP');
        $response->assertSee('Delete');
        $response->assertSee('Download Uploads Backup');
    }

    public function test_backup_store_requires_create_permission(): void
    {
        $admin = $this->makeAdminWithPermissions();
        $this->bindFakeBackupService();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.backups.store'));

        $response->assertForbidden();
    }

    public function test_backup_store_generates_archive_for_authorized_admin(): void
    {
        $admin = $this->makeAdminWithPermissions(['backups.create']);
        $service = $this->bindFakeBackupService();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.backups.store'));

        $response->assertRedirect(route('admin.backups.index'));
        $response->assertSessionHas('status', 'Backup generated successfully: backup_demo_20260312_010203.zip');
        $this->assertTrue($service->createCalled);
    }

    public function test_backup_download_returns_archive_for_authorized_admin(): void
    {
        $admin = $this->makeAdminWithPermissions(['backups.download']);
        $filename = 'backup_download_test_20260312_130000.zip';
        $path = storage_path('app/backups/'.$filename);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'zip-content');

        $service = $this->bindFakeBackupService([], $path);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.backups.download', ['filename' => $filename]));

        $response->assertOk();
        $response->assertDownload($filename);
        $this->assertSame($filename, $service->resolvedFilename);

        File::delete($path);
    }

    public function test_uploads_backup_requires_download_permission(): void
    {
        $admin = $this->makeAdminWithPermissions();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.system.backup.uploads'));

        $response->assertForbidden();
    }

    public function test_uploads_backup_downloads_zip_with_uploaded_files(): void
    {
        $admin = $this->makeAdminWithPermissions(['backups.download']);
        $uploadsRoot = storage_path('framework/testing/uploads_'.Str::lower(Str::random(8)));

        config(['filesystems.disks.public.root' => $uploadsRoot]);

        File::ensureDirectoryExists($uploadsRoot.'/kyc/documents');
        File::ensureDirectoryExists($uploadsRoot.'/kyc/optional');
        File::put($uploadsRoot.'/kyc/documents/front.jpg', 'front-image-content');
        File::put($uploadsRoot.'/kyc/optional/statement.pdf', 'pdf-content');

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.system.backup.uploads'));

        $response->assertOk();
        $response->assertDownload();

        /** @var \Symfony\Component\HttpFoundation\BinaryFileResponse $binaryResponse */
        $binaryResponse = $response->baseResponse;
        $zipPath = $binaryResponse->getFile()->getPathname();

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath);
        $this->assertSame(true, $opened);

        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if ($name !== false) {
                $entries[] = $name;
            }
        }
        $zip->close();

        $this->assertContains('kyc/documents/front.jpg', $entries);
        $this->assertContains('kyc/optional/statement.pdf', $entries);

        File::delete($zipPath);
        File::deleteDirectory($uploadsRoot);
    }

    public function test_backup_destroy_requires_download_permission(): void
    {
        $admin = $this->makeAdminWithPermissions();
        $filename = 'backup_delete_test_20260312_140000.zip';
        $this->bindFakeBackupService([], storage_path('app/backups/'.$filename));

        $response = $this->actingAs($admin, 'admin')
            ->delete(route('admin.backups.destroy', ['filename' => $filename]));

        $response->assertForbidden();
    }

    public function test_backup_destroy_deletes_archive_for_authorized_admin(): void
    {
        $admin = $this->makeAdminWithPermissions(['backups.download']);
        $filename = 'backup_delete_test_20260312_150000.zip';
        $service = $this->bindFakeBackupService([], storage_path('app/backups/'.$filename));

        $response = $this->actingAs($admin, 'admin')
            ->delete(route('admin.backups.destroy', ['filename' => $filename]));

        $response->assertRedirect(route('admin.backups.index'));
        $response->assertSessionHas('status', "Backup deleted successfully: {$filename}");
        $this->assertSame($filename, $service->deletedFilename);
    }
}
