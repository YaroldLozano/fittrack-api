<?php

namespace App\Models;

use PDO;

class GroupWorkoutParticipantModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function add(int $groupWorkoutId, int $userId, string $status): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO group_workout_participants (group_workout_id, user_id, status) VALUES (:group_workout_id, :user_id, :status)'
        );
        $stmt->execute(['group_workout_id' => $groupWorkoutId, 'user_id' => $userId, 'status' => $status]);
    }

    public function findOne(int $groupWorkoutId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM group_workout_participants WHERE group_workout_id = :group_workout_id AND user_id = :user_id'
        );
        $stmt->execute(['group_workout_id' => $groupWorkoutId, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findForGroupWorkout(int $groupWorkoutId): array
    {
        $stmt = $this->db->prepare(
            'SELECT gwp.*, u.username, u.name FROM group_workout_participants gwp
             INNER JOIN users u ON u.id = gwp.user_id
             WHERE gwp.group_workout_id = :group_workout_id'
        );
        $stmt->execute(['group_workout_id' => $groupWorkoutId]);
        return $stmt->fetchAll();
    }

    public function updateStatus(int $groupWorkoutId, int $userId, string $status): void
    {
        $stmt = $this->db->prepare(
            'UPDATE group_workout_participants SET status = :status WHERE group_workout_id = :group_workout_id AND user_id = :user_id'
        );
        $stmt->execute(['status' => $status, 'group_workout_id' => $groupWorkoutId, 'user_id' => $userId]);
    }

    public function countCompletedForUser(int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM group_workout_participants gwp
             INNER JOIN group_workouts gw ON gw.id = gwp.group_workout_id
             WHERE gwp.user_id = :user_id AND gwp.status = 'accepted' AND gw.status = 'completed'"
        );
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
