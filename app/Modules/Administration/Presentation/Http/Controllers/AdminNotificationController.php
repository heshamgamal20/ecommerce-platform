<?php

namespace App\Modules\Administration\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Modules\Administration\Presentation\Http\Requests\AdminNotificationRequest;
use Illuminate\Http\JsonResponse;

final class AdminNotificationController extends Controller
{
    public function index(AdminNotificationRequest $request): JsonResponse
    {
        $notifications = AdminNotification::query()->where('user_id', $request->user()->id)->latest('id')
            ->when($request->validated('unread_only'), fn ($query) => $query->whereNull('read_at'))
            ->paginate(min(max((int) ($request->validated('per_page') ?? 50), 1), 100));
        return response()->json(['data' => $notifications, 'unread_count' => AdminNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->count()]);
    }

    public function read(AdminNotificationRequest $request, int $notification): JsonResponse
    {
        $item = AdminNotification::query()->where('user_id', $request->user()->id)->findOrFail($notification);
        $item->update(['read_at' => now()]);
        return response()->json(['data' => $item->fresh()]);
    }

    public function readAll(AdminNotificationRequest $request): JsonResponse
    {
        $count = AdminNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['data' => ['marked_read' => $count]]);
    }
}
