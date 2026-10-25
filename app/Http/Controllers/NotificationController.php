<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notification\IndexNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepo
    ) {}

    /**
     * List only the authenticated person's reviews or owner replies.
     */
    public function index(IndexNotificationsRequest $request): JsonResponse
    {
        return response()->json([
            'notifications' => NotificationResource::collection(
                $this->notificationRepo->forUser($request->user(), (int) $request->validated('page', 1))
            )->response()->getData(true),
            'unread_count' => $this->notificationRepo->unreadCount($request->user()),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Reject unavailable or foreign activity before returning the updated count.
     */
    public function markRead(Request $request, string $notification): JsonResponse
    {
        $readAt = $this->notificationRepo->markRead($request->user(), $notification);
        abort_if($readAt === null, 404);

        return response()->json([
            'read_at' => $readAt,
            'unread_count' => $this->notificationRepo->unreadCount($request->user()),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Mark this person's current activity read using the existing cache.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        return response()->json([
            'read_at' => $this->notificationRepo->markAllRead($request->user()),
            'unread_count' => $this->notificationRepo->unreadCount($request->user()),
        ])->header('Cache-Control', 'private, no-store');
    }
}
