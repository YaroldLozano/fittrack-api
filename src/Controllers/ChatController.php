<?php

namespace App\Controllers;

use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\RateLimitException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ChatService;
use InvalidArgumentException;

class ChatController
{
    public function __construct(private readonly ChatService $chat)
    {
    }

    public function conversations(Request $request): void
    {
        Response::success(['conversations' => $this->chat->listConversations((int) $request->userId)]);
    }

    public function createConversation(Request $request): void
    {
        try {
            $conversation = $this->chat->getOrCreateConversation(
                (int) $request->userId,
                (int) $request->input('friend_id', 0)
            );
            Response::success(['conversation' => $conversation], 201);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        } catch (ForbiddenException $e) {
            Response::error($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function messages(Request $request): void
    {
        try {
            $before = $request->input('before');
            $result = $this->chat->getMessages(
                (int) $request->userId,
                (int) $request->params['id'],
                $before !== null ? (int) $before : null,
                (int) $request->input('limit', 50)
            );
            Response::success($result);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function sendMessage(Request $request): void
    {
        try {
            $message = $this->chat->sendMessage(
                (int) $request->userId,
                (int) $request->params['id'],
                (string) $request->input('message', '')
            );
            Response::success(['message_data' => $message], 201);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        } catch (ForbiddenException $e) {
            Response::error($e->getMessage(), 403);
        } catch (RateLimitException $e) {
            Response::error($e->getMessage(), 429);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function markRead(Request $request): void
    {
        try {
            $this->chat->markRead((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Conversación marcada como leída']);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function deleteMessage(Request $request): void
    {
        try {
            $this->chat->deleteMessage((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Mensaje eliminado']);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        } catch (ForbiddenException $e) {
            Response::error($e->getMessage(), 403);
        }
    }
}
