<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('audit-logs.view'), 403);

        $auditReady = Schema::hasTable('audit_logs');
        if (! $auditReady) {
            return view('admin.audit-logs.index', [
                'auditReady' => false,
                'auditLogs' => collect(),
                'availableModels' => collect(),
                'availableEvents' => collect(),
            ]);
        }

        $query = AuditLog::query()->latest();

        if ($request->filled('event')) {
            $query->where('event', $request->string('event')->toString());
        }

        if ($request->filled('model')) {
            $query->where('auditable_type', $request->string('model')->toString());
        }

        if ($request->filled('actor')) {
            $actor = $request->string('actor')->trim()->toString();
            $query->where(function ($q) use ($actor): void {
                $q->where('actor_name', 'like', "%{$actor}%")
                    ->orWhere('actor_id', 'like', "%{$actor}%");
            });
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('auditable_type', 'like', "%{$search}%")
                    ->orWhere('auditable_id', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to')->toString());
        }

        $auditLogs = $query->paginate(50)->withQueryString();

        /** @var Collection<int, string> $availableModels */
        $availableModels = AuditLog::query()
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type');

        /** @var Collection<int, string> $availableEvents */
        $availableEvents = AuditLog::query()
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        return view('admin.audit-logs.index', compact('auditReady', 'auditLogs', 'availableModels', 'availableEvents'));
    }
}
