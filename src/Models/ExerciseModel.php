<?php

namespace App\Models;

use PDO;

class ExerciseModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** System exercises (user_id NULL) + this user's own custom exercises. */
    public function findVisibleTo(int $userId, ?int $muscleGroupId = null): array
    {
        $sql = 'SELECT e.*, mg.name AS muscle_group_name
                FROM exercises e
                JOIN muscle_groups mg ON mg.id = e.muscle_group_id
                WHERE e.is_active = 1 AND (e.user_id IS NULL OR e.user_id = :user_id)';
        $params = ['user_id' => $userId];

        if ($muscleGroupId !== null) {
            $sql .= ' AND e.muscle_group_id = :muscle_group_id';
            $params['muscle_group_id'] = $muscleGroupId;
        }

        $sql .= ' ORDER BY mg.name, e.name';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findVisibleById(int $userId, int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT e.*, mg.name AS muscle_group_name
             FROM exercises e
             JOIN muscle_groups mg ON mg.id = e.muscle_group_id
             WHERE e.id = :id AND e.is_active = 1 AND (e.user_id IS NULL OR e.user_id = :user_id)'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Only matches rows the user owns (system exercises are not editable/deletable). */
    public function findOwnedBy(int $userId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM exercises WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO exercises
                (user_id, muscle_group_id, name, description, instructions, image_url, video_url, equipment, difficulty_level)
             VALUES
                (:user_id, :muscle_group_id, :name, :description, :instructions, :image_url, :video_url, :equipment, :difficulty_level)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'muscle_group_id' => $data['muscle_group_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'equipment' => $data['equipment'] ?? null,
            'difficulty_level' => $data['difficulty_level'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateOwned(int $userId, int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE exercises SET
                muscle_group_id = :muscle_group_id,
                name = :name,
                description = :description,
                instructions = :instructions,
                image_url = :image_url,
                video_url = :video_url,
                equipment = :equipment,
                difficulty_level = :difficulty_level
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'muscle_group_id' => $data['muscle_group_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'equipment' => $data['equipment'] ?? null,
            'difficulty_level' => $data['difficulty_level'] ?? null,
            'id' => $id,
            'user_id' => $userId,
        ]);
    }

    public function softDeleteOwned(int $userId, int $id): void
    {
        $stmt = $this->db->prepare('UPDATE exercises SET is_active = 0 WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
}
