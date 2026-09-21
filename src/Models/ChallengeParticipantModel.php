<?php

namespace App\Models;

use PDO;

class ChallengeParticipantModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function add(int $challengeId, int $userId, string $status): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO challenge_participants (challenge_id, user_id, status) VALUES (:challenge_id, :user_id, :status)'
        );
        $stmt->execute(['challenge_id' => $challengeId, 'user_id' => $userId, 'status' => $status]);
    }

    public function findOne(int $challengeId, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM challenge_participants WHERE challenge_id = :challenge_id AND user_id = :user_id');
        $stmt->execute(['challenge_id' => $challengeId, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findForChallenge(int $challengeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT cp.*, u.username, u.name FROM challenge_participants cp
             INNER JOIN users u ON u.id = cp.user_id
             WHERE cp.challenge_id = :challenge_id'
        );
        $stmt->execute(['challenge_id' => $challengeId]);
        return $stmt->fetchAll();
    }

    public function updateStatus(int $challengeId, int $userId, string $status): void
    {
        $stmt = $this->db->prepare(
            'UPDATE challenge_participants SET status = :status WHERE challenge_id = :challenge_id AND user_id = :user_id'
        );
        $stmt->execute(['status' => $status, 'challenge_id' => $challengeId, 'user_id' => $userId]);
    }

    public function updateProgress(int $challengeId, int $userId, float $value): void
    {
        $stmt = $this->db->prepare(
            'UPDATE challenge_participants SET progress_value = :value WHERE challenge_id = :challenge_id AND user_id = :user_id'
        );
        $stmt->execute(['value' => $value, 'challenge_id' => $challengeId, 'user_id' => $userId]);
    }

    public function countAcceptedParticipations(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM challenge_participants WHERE user_id = :user_id AND status = 'accepted'");
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function countWins(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM challenges WHERE winner_user_id = :user_id AND status = 'completed'");
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
