<?php

namespace App\Models;

use PDO;
use PDOException;

class XpTransactionModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Inserta una transacción de XP. Devuelve false (sin lanzar) si ya existía
     * una transacción con el mismo (user_id, source, source_id) — es el
     * mecanismo antifraude: cada evento solo puede otorgar XP una vez.
     */
    public function tryInsert(int $userId, int $amount, string $source, string $sourceId, ?string $description, ?int $seasonId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO xp_transactions (user_id, amount, source, source_id, description, season_id)
             VALUES (:user_id, :amount, :source, :source_id, :description, :season_id)'
        );

        try {
            $stmt->execute([
                'user_id' => $userId,
                'amount' => $amount,
                'source' => $source,
                'source_id' => $sourceId,
                'description' => $description,
                'season_id' => $seasonId,
            ]);
            return true;
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                return false; // ya otorgada para este evento
            }
            throw $e;
        }
    }

    public function countTodayBySource(int $userId, string $source): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM xp_transactions WHERE user_id = :user_id AND source = :source AND DATE(created_at) = CURDATE()'
        );
        $stmt->execute(['user_id' => $userId, 'source' => $source]);
        return (int) $stmt->fetchColumn();
    }

    public function sumForUserBetween(int $userId, string $from, string $to): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM xp_transactions
             WHERE user_id = :user_id AND created_at >= :from AND created_at < :to'
        );
        $stmt->execute(['user_id' => $userId, 'from' => $from, 'to' => $to]);
        return (int) $stmt->fetchColumn();
    }

    public function recentForUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM xp_transactions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT ' . (int) $limit
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
