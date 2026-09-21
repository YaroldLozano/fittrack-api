<?php

namespace App\Services;

use App\Models\ExerciseModel;
use App\Models\MuscleGroupModel;
use InvalidArgumentException;

class ExerciseService
{
    public function __construct(
        private readonly ExerciseModel $exercises,
        private readonly MuscleGroupModel $muscleGroups
    ) {
    }

    public function list(int $userId, ?int $muscleGroupId): array
    {
        return $this->exercises->findVisibleTo($userId, $muscleGroupId);
    }

    public function create(int $userId, array $data): array
    {
        $this->validate($data);
        $id = $this->exercises->create($userId, $data);
        return $this->exercises->findVisibleById($userId, $id);
    }

    public function update(int $userId, int $id, array $data): array
    {
        $this->validate($data);

        if ($this->exercises->findOwnedBy($userId, $id) === null) {
            throw new InvalidArgumentException('Ejercicio no encontrado');
        }

        $this->exercises->updateOwned($userId, $id, $data);
        return $this->exercises->findVisibleById($userId, $id);
    }

    public function delete(int $userId, int $id): void
    {
        if ($this->exercises->findOwnedBy($userId, $id) === null) {
            throw new InvalidArgumentException('Ejercicio no encontrado');
        }

        $this->exercises->softDeleteOwned($userId, $id);
    }

    private function validate(array $data): void
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('El nombre del ejercicio es requerido');
        }

        if (empty($data['muscle_group_id']) || !$this->muscleGroups->exists((int) $data['muscle_group_id'])) {
            throw new InvalidArgumentException('Grupo muscular inválido');
        }
    }
}
