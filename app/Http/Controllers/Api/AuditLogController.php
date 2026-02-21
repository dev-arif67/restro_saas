<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends BaseApiController
{
    /**
     * List audit logs with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with(['user:id,name,email,role', 'tenant:id,name,slug']);

        // Filter by tenant
        if ($tenantId = $request->get('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        // Filter by user
        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }

        // Filter by action
        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        // Filter by subject type
        if ($subjectType = $request->get('subject_type')) {
            // Allow partial class name matching
            $query->where('subject_type', 'like', "%{$subjectType}%");
        }

        // Filter by date range
        if ($from = $request->get('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->where('created_at', '<=', $to);
        }

        // Search in old_values or new_values JSON
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('old_values', 'like', "%{$search}%")
                    ->orWhere('new_values', 'like', "%{$search}%");
            });
        }

        return $this->paginated($query->latest(), $request->get('per_page', 20));
    }

    /**
     * Show a specific audit log entry.
     */
    public function show(int $id): JsonResponse
    {
        $log = AuditLog::with(['user:id,name,email,role', 'tenant:id,name,slug'])
            ->find($id);

        if (!$log) {
            return $this->notFound('Audit log not found');
        }

        return $this->success($log);
    }

    /**
     * Get available action types for filtering.
     */
    public function actions(): JsonResponse
    {
        $actions = AuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return $this->success($actions);
    }

    /**
     * Get audit log statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);

        $byAction = AuditLog::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->get();

        $byUser = AuditLog::where('created_at', '>=', now()->subDays($days))
            ->with('user:id,name')
            ->selectRaw('user_id, COUNT(*) as count')
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $bySubject = AuditLog::where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('subject_type')
            ->selectRaw('subject_type, COUNT(*) as count')
            ->groupBy('subject_type')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'subject_type' => class_basename($item->subject_type),
                    'full_type' => $item->subject_type,
                    'count' => $item->count,
                ];
            });

        $timeline = AuditLog::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $this->success([
            'period_days' => $days,
            'total' => AuditLog::where('created_at', '>=', now()->subDays($days))->count(),
            'by_action' => $byAction,
            'by_user' => $byUser,
            'by_subject' => $bySubject,
            'timeline' => $timeline,
        ]);
    }

    /**
     * Export audit logs to CSV.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = AuditLog::with(['user:id,name', 'tenant:id,name']);

        if ($from = $request->get('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->where('created_at', '<=', $to);
        }

        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        $logs = $query->orderByDesc('created_at')->limit(5000)->get();
        $filename = 'audit-logs-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID',
                'Timestamp',
                'User',
                'Tenant',
                'Action',
                'Subject Type',
                'Subject ID',
                'IP Address',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user?->name ?? 'System',
                    $log->tenant?->name ?? 'N/A',
                    $log->action,
                    $log->subject_type ? class_basename($log->subject_type) : 'N/A',
                    $log->subject_id ?? 'N/A',
                    $log->ip_address ?? 'N/A',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
