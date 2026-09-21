<?php

namespace App\Models;

use PDO;

class MessageModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Paginación por cursor: los `limit` mensajes no borrados más recientes anteriores
     * a `$beforeId` (o los más recientes si es null), en orden ascendente para pintar.
     */
    public function paginate(int $conversationId, ?int $beforeId, int $limit): array
    {
        if ($beforeId !== null) {
            $stmt = $this->db->prepare(
                'SELECT * FROM messages
                 WHERE conversation_id = :cid AND deleted_at IS NULL AND id < :before
                 ORDER BY id DESC LIMIT ' . (int) $limit
            );
            $stmt->execute(['cid' => $conversationId, 'before' => $beforeId]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM messages
                 WHERE conversation_id = :cid AND deleted_at IS NULL
                 ORDER BY id DESC LIMIT ' . (int) $limit
            );
            $stmt->execute(['cid' => $conversationId]);
        }

        $rows = $stmt->fetchAll();
        return array_reverse($rows);
    }

    public function hasMoreBefore(int $conversationId, int $oldestIdInPage): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM messages WHERE conversation_id = :cid AND deleted_at IS NULL AND id < :id LIMIT 1'
        );
        $stmt->execute(['cid' => $conversationId, 'id' => $oldestIdInPage]);
        return (bool) $stmt->fetchColumn();
    }

    public function create(int $conversationId, int $senderId, string $message): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO messages (conversation_id, sender_id, message) VALUES (:cid, :sender, :message)'
        );
        $stmt->execute(['cid' => $conversationId, 'sender' => $senderId, 'message' => $message]);
        $id = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('SELECT * FROM messages WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM messages WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Marca como leídos todos los mensajes recibidos (no enviados por el propio lector) sin leer aún. */
    public function markAllReadInConversation(int $conversationId, int $readerId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE messages SET read_at = NOW()
             WHERE conversation_id = :cid AND sender_id != :reader AND read_at IS NULL AND deleted_at IS NULL'
        );
        $stmt->execute(['cid' => $conversationId, 'reader' => $readerId]);
    }

    public function countRecentBySender(int $senderId, int $seconds): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM messages WHERE sender_id = :sender AND created_at > NOW() - INTERVAL ' . (int) $seconds . ' SECOND'
        );
        $stmt->execute(['sender' => $senderId]);
        return (int) $stmt->fetchColumn();
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE messages SET deleted_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
