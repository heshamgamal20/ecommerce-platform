<?php

namespace App\Modules\Administration\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Modules\Administration\Presentation\Http\Requests\AdminAuditRequest;
use Illuminate\Http\JsonResponse;

final class AdminAuditController extends Controller
{
    public function index(AdminAuditRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $logs = AuditLog::query()->with('actor:id,name,email')->latest('id')
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['actor_id'] ?? null, fn ($query, $actorId) => $query->where('actor_id', $actorId))
            ->when($filters['target_type'] ?? null, fn ($query, $type) => $query->where('target_type', $type))
            ->when($filters['target_id'] ?? null, fn ($query, $targetId) => $query->where('target_id', $targetId))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->paginate(min(max((int) ($filters['per_page'] ?? 50), 1), 100));
        return response()->json(['data' => $logs]);
    }
}
