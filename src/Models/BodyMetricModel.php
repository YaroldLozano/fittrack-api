<?php

namespace App\Models;

use PDO;

class BodyMetricModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findAllForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM body_metrics WHERE user_id = :user_id ORDER BY recorded_date'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function latestForUser(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM body_metrics WHERE user_id = :user_id ORDER BY recorded_date DESC LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** One row per (user, date) — upsert so re-submitting the same day updates it. */
    public function upsert(int $userId, array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO body_metrics
                (user_id, recorded_date, weight, height, body_fat_percentage, chest, waist, arm, leg, hip)
             VALUES
                (:user_id, :recorded_date, :weight, :height, :body_fat_percentage, :chest, :waist, :arm, :leg, :hip)
             ON DUPLICATE KEY UPDATE
                weight = VALUES(weight), height = VALUES(height),
                body_fat_percentage = VALUES(body_fat_percentage),
                chest = VALUES(chest), waist = VALUES(waist), arm = VALUES(arm),
                leg = VALUES(leg), hip = VALUES(hip)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'recorded_date' => $data['recorded_date'],
            'weight' => $data['weight'] ?? null,
            'height' => $data['height'] ?? null,
            'body_fat_percentage' => $data['body_fat_percentage'] ?? null,
            'chest' => $data['chest'] ?? null,
            'waist' => $data['waist'] ?? null,
            'arm' => $data['arm'] ?? null,
            'leg' => $data['leg'] ?? null,
            'hip' => $data['hip'] ?? null,
        ]);
    }
}
