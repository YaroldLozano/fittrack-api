<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\NotificationService;

class NotificationController
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function index(Request $request): void
    {
        Response::success([
            'notifications' => $this->notifications->list((int) $request->userId),
            'unread_count' => $this->notifications->unreadCount((int) $request->userId),
        ]);
    }

    public function markRead(Request $request): void
    {
        $this->notifications->markRead((int) $request->userId, (int) $request->params['id']);
        Response::success(['message' => 'Notificación marcada como leída']);
    }

    public function markAllRead(Request $request): void
    {
        $this->notifications->markAllRead((int) $request->userId);
        Response::success(['message' => 'Todas las notificaciones marcadas como leídas']);
    }
}
