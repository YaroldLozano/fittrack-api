<?php

namespace App\Models;

use PDO;

class ChallengeModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(int $creatorId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO challenges (creator_id, type, title, exercise_id, starts_at, ends_at, status)
             VALUES (:creator_id, :type, :title, :exercise_id, :starts_at, :ends_at, :status)'
        );
        $stmt->execute([
            'creator_id' => $creatorId,
            'type' => $data['type'],
            'title' => $data['title'],
            'exercise_id' => $data['exercise_id'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'status' => $data['starts_at'] <= date('Y-m-d') ? 'active' : 'pending',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM challenges WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, cp.status AS my_status, cp.progress_value AS my_progress
             FROM challenges c
             INNER JOIN challenge_participants cp ON cp.challenge_id = c.id
             WHERE cp.user_id = :user_id
             ORDER BY c.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findActiveExpired(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM challenges WHERE status = 'active' AND ends_at < CURDATE()");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function setStatus(int $id, string $status, ?int $winnerUserId = null): void
    {
        $stmt = $this->db->prepare('UPDATE challenges SET status = :status, winner_user_id = :winner WHERE id = :id');
        $stmt->execute(['status' => $status, 'winner' => $winnerUserId, 'id' => $id]);
    }

    public function activatePending(): void
    {
        $this->db->exec("UPDATE challenges SET status = 'active' WHERE status = 'pending' AND starts_at <= CURDATE()");
    }
}
