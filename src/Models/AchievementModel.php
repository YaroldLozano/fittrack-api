<?php

namespace App\Models;

use PDO;

class AchievementModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function all(): array
    {
        $stmt = $this->db->query('SELECT * FROM achievements ORDER BY category, requirement_value');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM achievements WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
