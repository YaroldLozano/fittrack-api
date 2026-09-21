<?php

namespace App\Models;

use PDO;

class FriendRequestModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(int $requesterId, int $addresseeId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO friend_requests (requester_id, addressee_id, status) VALUES (:requester_id, :addressee_id, 'pending')"
        );
        $stmt->execute(['requester_id' => $requesterId, 'addressee_id' => $addresseeId]);
        return (int) $this->db->lastInsertId();
    }

    public function findPendingBetween(int $userA, int $userB): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM friend_requests
             WHERE status = 'pending' AND
                   ((requester_id = :a1 AND addressee_id = :b1) OR (requester_id = :b2 AND addressee_id = :a2))"
        );
        $stmt->execute(['a1' => $userA, 'b1' => $userB, 'b2' => $userB, 'a2' => $userA]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM friend_requests WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findIncomingPending(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT fr.*, u.id AS from_id, u.username AS from_username, u.name AS from_name
             FROM friend_requests fr
             INNER JOIN users u ON u.id = fr.requester_id
             WHERE fr.addressee_id = :user_id AND fr.status = 'pending'
             ORDER BY fr.created_at DESC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findOutgoingPending(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT fr.*, u.id AS to_id, u.username AS to_username, u.name AS to_name
             FROM friend_requests fr
             INNER JOIN users u ON u.id = fr.addressee_id
             WHERE fr.requester_id = :user_id AND fr.status = 'pending'
             ORDER BY fr.created_at DESC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE friend_requests SET status = :status, responded_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }
}
