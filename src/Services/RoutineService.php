<?php

namespace App\Services;

use App\Models\RoutineDayModel;
use App\Models\RoutineExerciseModel;
use App\Models\RoutineModel;
use InvalidArgumentException;
use PDO;

class RoutineService
{
    public function __construct(
        private readonly PDO $db,
        private readonly RoutineModel $routines,
        private readonly RoutineDayModel $routineDays,
        private readonly RoutineExerciseModel $routineExercises
    ) {
    }

    public function list(int $userId): array
    {
        $routines = $this->routines->findAllForUser($userId);
        return array_map(fn ($routine) => $this->attachDays($routine), $routines);
    }

    public function get(int $userId, int $id): array
    {
        $routine = $this->routines->findOwnedBy($userId, $id);
        if ($routine === null) {
            throw new InvalidArgumentException('Rutina no encontrada');
        }
        return $this->attachDays($routine);
    }

    public function create(int $userId, array $data): array
    {
        $this->validate($data);

        $this->db->beginTransaction();
        try {
            $routineId = $this->routines->create($userId, $data);
            $this->saveDays($routineId, $data['days'] ?? []);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->get($userId, $routineId);
    }

    public function update(int $userId, int $id, array $data): array
    {
        $this->validate($data);

        if ($this->routines->findOwnedBy($userId, $id) === null) {
            throw new InvalidArgumentException('Rutina no encontrada');
        }

        $this->db->beginTransaction();
        try {
            $this->routines->updateOwned($userId, $id, $data);
            if (array_key_exists('days', $data)) {
                $this->routineDays->deleteByRoutine($id);
                $this->saveDays($id, $data['days']);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->get($userId, $id);
    }

    public function updateStatus(int $userId, int $id, string $status): array
    {
        if (!in_array($status, ['active', 'paused', 'completed'], true)) {
            throw new InvalidArgumentException('Estado inválido');
        }
        if ($this->routines->findOwnedBy($userId, $id) === null) {
            throw new InvalidArgumentException('Rutina no encontrada');
        }

        $this->routines->updateStatusOwned($userId, $id, $status);
        return $this->get($userId, $id);
    }

    public function duplicate(int $userId, int $id): array
    {
        $original = $this->get($userId, $id);

        $this->db->beginTransaction();
        try {
            $newId = $this->routines->create($userId, [
                'name' => $original['name'] . ' (copia)',
                'description' => $original['description'],
                'goal' => $original['goal'],
                'start_date' => $original['start_date'],
                'end_date' => $original['end_date'],
                'status' => 'active',
            ]);

            $days = array_map(function ($day) {
                return [
                    'day_of_week' => $day['day_of_week'],
                    'label' => $day['label'],
                    'is_rest_day' => $day['is_rest_day'],
                    'exercises' => array_map(fn ($ex) => [
                        'exercise_id' => $ex['exercise_id'],
                        'sets' => $ex['sets'],
                        'reps' => $ex['reps'],
                        'target_weight' => $ex['target_weight'],
                        'rest_seconds' => $ex['rest_seconds'],
                        'notes' => $ex['notes'],
                    ], $day['exercises']),
                ];
            }, $original['days']);

            $this->saveDays($newId, $days);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->get($userId, $newId);
    }

    public function delete(int $userId, int $id): void
    {
        if ($this->routines->findOwnedBy($userId, $id) === null) {
            throw new InvalidArgumentException('Rutina no encontrada');
        }
        $this->routines->deleteOwned($userId, $id);
    }

    private function saveDays(int $routineId, array $days): void
    {
        foreach ($days as $index => $day) {
            if (!isset($day['day_of_week'])) {
                throw new InvalidArgumentException('Cada día requiere day_of_week (0-6)');
            }
            $dayId = $this->routineDays->create($routineId, $day, $index);

            foreach (($day['exercises'] ?? []) as $exIndex => $exercise) {
                if (empty($exercise['exercise_id'])) {
                    throw new InvalidArgumentException('Cada ejercicio de rutina requiere exercise_id');
                }
                $this->routineExercises->create($dayId, $exercise, $exIndex);
            }
        }
    }

    private function attachDays(array $routine): array
    {
        $days = $this->routineDays->findByRoutine((int) $routine['id']);
        $routine['days'] = array_map(function ($day) {
            $day['exercises'] = $this->routineExercises->findByDay((int) $day['id']);
            return $day;
        }, $days);
        return $routine;
    }

    private function validate(array $data): void
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('El nombre de la rutina es requerido');
        }
    }
}
