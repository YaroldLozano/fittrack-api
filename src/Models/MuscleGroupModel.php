<?php

namespace App\Models;

use PDO;

class MuscleGroupModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function all(): array
    {
        return $this->db->query('SELECT id, name FROM muscle_groups ORDER BY name')->fetchAll();
    }

    public function exists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM muscle_groups WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetchColumn();
    }
}
