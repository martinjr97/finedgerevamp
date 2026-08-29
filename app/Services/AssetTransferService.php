<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssetTransferService
{
    /**
     * Transfer asset ownership to another employee and record the trail.
     */
    public function transfer(Asset $asset, ?int $toEmployeeId, ?string $reason, Admin $admin): AssetTransfer
    {
        $fromEmployeeId = $asset->employee_id;

        if ($fromEmployeeId === $toEmployeeId) {
            throw new \InvalidArgumentException('The asset is already assigned to this employee.');
        }

        return DB::transaction(function () use ($asset, $fromEmployeeId, $toEmployeeId, $reason, $admin) {
            $asset->update([
                'employee_id' => $toEmployeeId,
                'updated_by' => $admin->id,
            ]);

            return $this->logOwnerChange($asset, $fromEmployeeId, $toEmployeeId, $reason, $admin);
        });
    }

    /**
     * Record owner change trail without updating the asset (asset already saved elsewhere).
     */
    public function logOwnerChange(
        Asset $asset,
        ?int $fromEmployeeId,
        ?int $toEmployeeId,
        ?string $reason,
        Admin $admin
    ): AssetTransfer {
        $transfer = $this->recordTransfer($asset, $fromEmployeeId, $toEmployeeId, $reason, $admin);

        $this->recordAuditLog($asset, $transfer, $admin);

        return $transfer;
    }

    /**
     * Record a transfer row without changing the asset (for historical logging from other flows).
     */
    public function recordTransfer(
        Asset $asset,
        ?int $fromEmployeeId,
        ?int $toEmployeeId,
        ?string $reason,
        Admin $admin
    ): AssetTransfer {
        if ($fromEmployeeId === $toEmployeeId) {
            throw new \InvalidArgumentException('From and to employee must differ.');
        }

        return AssetTransfer::create([
            'asset_id' => $asset->id,
            'from_employee_id' => $fromEmployeeId,
            'to_employee_id' => $toEmployeeId,
            'reason' => $reason,
            'transferred_by' => $admin->id,
        ]);
    }

    public function employeeLabel(?int $employeeId): string
    {
        if ($employeeId === null) {
            return 'Unassigned';
        }

        $employee = Employee::withTrashed()->find($employeeId);

        return $employee?->full_name ?? 'Unknown employee';
    }

    private function recordAuditLog(Asset $asset, AssetTransfer $transfer, Admin $admin): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $request = request();
        $actorName = $admin->full_name ?: $admin->email;

        AuditLog::withoutEvents(function () use ($asset, $transfer, $admin, $actorName, $request): void {
            AuditLog::query()->create([
                'event' => 'asset_owner_transferred',
                'auditable_type' => Asset::class,
                'auditable_id' => (string) $asset->getKey(),
                'old_values' => [
                    'employee_id' => $transfer->from_employee_id,
                    'employee_name' => $this->employeeLabel($transfer->from_employee_id),
                ],
                'new_values' => [
                    'employee_id' => $transfer->to_employee_id,
                    'employee_name' => $this->employeeLabel($transfer->to_employee_id),
                ],
                'changed_fields' => ['employee_id'],
                'actor_type' => $admin::class,
                'actor_id' => (string) $admin->getKey(),
                'actor_name' => $actorName,
                'actor_guard' => 'admin',
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'url' => $request?->fullUrl(),
                'http_method' => $request?->method(),
                'metadata' => [
                    'asset_transfer_id' => $transfer->id,
                    'reason' => $transfer->reason,
                ],
            ]);
        });
    }
}
