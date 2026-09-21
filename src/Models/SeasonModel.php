<?php

namespace App\Models;

use PDO;

class SeasonModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findActive(): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM seasons WHERE status = 'active' ORDER BY starts_at DESC LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name, string $startsAt, string $endsAt): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO seasons (name, starts_at, ends_at, status) VALUES (:name, :starts_at, :ends_at, "active")'
        );
        $stmt->execute(['name' => $name, 'starts_at' => $startsAt, 'ends_at' => $endsAt]);
        return (int) $this->db->lastInsertId();
    }

    public function close(int $seasonId): void
    {
        $stmt = $this->db->prepare("UPDATE seasons SET status = 'closed' WHERE id = :id");
        $stmt->execute(['id' => $seasonId]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM seasons WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
