<?php

namespace App\Services;

use App\Config\GamificationRules;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\RateLimitException;
use App\Models\BlockedUserModel;
use App\Models\ConversationModel;
use App\Models\FriendshipModel;
use App\Models\MessageModel;
use App\Models\UserModel;
use InvalidArgumentException;

class ChatService
{
    private const MAX_MESSAGE_LENGTH = 2000;
    private const RATE_LIMIT_MESSAGES = 20;
    private const RATE_LIMIT_WINDOW_SECONDS = 60;
    private const DEFAULT_PAGE_SIZE = 50;
    private const MAX_PAGE_SIZE = 100;

    public function __construct(
        private readonly ConversationModel $conversations,
        private readonly MessageModel $messages,
        private readonly UserModel $users,
        private readonly FriendshipModel $friendships,
        private readonly BlockedUserModel $blocked
    ) {
    }

    public function listConversations(int $userId): array
    {
        return array_map(
            fn (array $row) => $this->rowToConversationDto($row, $userId),
            $this->conversations->listForUser($userId)
        );
    }

    /** Crea (o reutiliza) la conversación 1:1 entre el usuario autenticado y un amigo confirmado. */
    public function getOrCreateConversation(int $userId, int $friendId): array
    {
        if ($userId === $friendId) {
            throw new InvalidArgumentException('No puedes iniciar una conversación contigo mismo');
        }
        if ($this->users->findById($friendId) === null) {
            throw new NotFoundException('Usuario no encontrado');
        }
        if (!$this->friendships->areFriends($userId, $friendId)) {
            throw new ForbiddenException('Solo puedes escribir a tus amigos');
        }
        if ($this->blocked->isBlockedEitherWay($userId, $friendId)) {
            throw new ForbiddenException('No puedes iniciar esta conversación');
        }

        $conversation = $this->conversations->create($userId, $friendId);
        return $this->rowToConversationDto($conversation, $userId);
    }

    /** @return array{conversation: array, messages: array<int, array>, has_more: bool} */
    public function getMessages(int $userId, int $conversationId, ?int $before, int $limit): array
    {
        $conversation = $this->requireOwnedConversation($userId, $conversationId);
        $limit = max(1, min($limit ?: self::DEFAULT_PAGE_SIZE, self::MAX_PAGE_SIZE));

        $page = $this->messages->paginate($conversationId, $before, $limit);
        $hasMore = $page !== [] && $this->messages->hasMoreBefore($conversationId, (int) $page[0]['id']);

        return [
            'conversation' => $this->rowToConversationDto($conversation, $userId),
            'messages' => array_map([$this, 'rowToMessageDto'], $page),
            'has_more' => $hasMore,
        ];
    }

    public function sendMessage(int $userId, int $conversationId, string $rawMessage): array
    {
        $conversation = $this->requireOwnedConversation($userId, $conversationId);
        $friendId = $this->conversations->otherUserId($conversation, $userId);

        // Se revalida en cada envío (no solo al crear la conversación): un bloqueo o
        // una amistad rota después de crear la conversación corta el chat de inmediato.
        if (!$this->friendships->areFriends($userId, $friendId)) {
            throw new ForbiddenException('Ya no son amigos');
        }
        if ($this->blocked->isBlockedEitherWay($userId, $friendId)) {
            throw new ForbiddenException('No puedes enviar mensajes a este usuario');
        }

        $message = trim($rawMessage);
        if ($message === '') {
            throw new InvalidArgumentException('El mensaje no puede estar vacío');
        }
        if (!mb_check_encoding($message, 'UTF-8')) {
            throw new InvalidArgumentException('El mensaje contiene caracteres inválidos');
        }
        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new InvalidArgumentException('El mensaje es demasiado largo');
        }

        if ($this->messages->countRecentBySender($userId, self::RATE_LIMIT_WINDOW_SECONDS) >= self::RATE_LIMIT_MESSAGES) {
            throw new RateLimitException('Estás enviando mensajes demasiado rápido, espera un momento');
        }

        $row = $this->messages->create($conversationId, $userId, $message);
        $this->conversations->touch($conversationId);

        return $this->rowToMessageDto($row);
    }

    public function markRead(int $userId, int $conversationId): void
    {
        $this->requireOwnedConversation($userId, $conversationId);
        $this->messages->markAllReadInConversation($conversationId, $userId);
    }

    public function deleteMessage(int $userId, int $messageId): void
    {
        $message = $this->messages->find($messageId);
        if ($message === null) {
            throw new NotFoundException('Mensaje no encontrado');
        }

        // Ownership del mensaje Y de la conversación a la que pertenece (nunca solo uno).
        $this->requireOwnedConversation($userId, (int) $message['conversation_id']);
        if ((int) $message['sender_id'] !== $userId) {
            throw new ForbiddenException('Solo puedes borrar tus propios mensajes');
        }

        $this->messages->softDelete($messageId);
    }

    private function requireOwnedConversation(int $userId, int $conversationId): array
    {
        $conversation = $this->conversations->findOwnedBy($userId, $conversationId);
        if ($conversation === null) {
            throw new NotFoundException('Conversación no encontrada');
        }
        return $conversation;
    }

    private function rowToConversationDto(array $row, int $userId): array
    {
        $friendId = isset($row['friend_id'])
            ? (int) $row['friend_id']
            : $this->conversations->otherUserId($row, $userId);

        if (isset($row['friend_username'])) {
            $friendUsername = $row['friend_username'];
            $friendName = $row['friend_name'];
            $friendAvatarMediaId = $row['friend_avatar_media_id'] ?? null;
            $friendLevel = (int) ($row['friend_level'] ?? 1);
        } else {
            $friend = $this->users->findById($friendId);
            $friendUsername = $friend['username'] ?? '';
            $friendName = $friend['name'] ?? null;
            $friendAvatarMediaId = $friend['avatar_media_id'] ?? null;
            $friendLevel = 1;
        }

        $levelInfo = GamificationRules::levelInfo($friendLevel);

        $lastMessage = null;
        if (!empty($row['last_message_id'])) {
            $lastMessage = [
                'id' => (int) $row['last_message_id'],
                'message' => $row['last_message'],
                'sender_id' => (int) $row['last_message_sender_id'],
                'created_at' => $row['last_message_created_at'],
            ];
        }

        return [
            'id' => (int) $row['id'],
            'friend' => [
                'id' => $friendId,
                'username' => $friendUsername,
                'name' => $friendName,
                'avatar_media_id' => $friendAvatarMediaId !== null ? (int) $friendAvatarMediaId : null,
                'level' => $friendLevel,
                'level_name' => $levelInfo['name'],
                'badge' => $levelInfo['badge'],
            ],
            'last_message' => $lastMessage,
            'unread_count' => (int) ($row['unread_count'] ?? 0),
            'updated_at' => $row['updated_at'],
            'blocked' => $this->blocked->isBlockedEitherWay($userId, $friendId),
        ];
    }

    private function rowToMessageDto(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'conversation_id' => (int) $row['conversation_id'],
            'sender_id' => (int) $row['sender_id'],
            'message' => $row['message'],
            'created_at' => $row['created_at'],
            'read_at' => $row['read_at'],
        ];
    }
}
