<?php

namespace App\Services;

use App\Models\FriendshipModel;
use App\Models\GroupWorkoutModel;
use App\Models\GroupWorkoutParticipantModel;
use InvalidArgumentException;

class GroupWorkoutService
{
    public function __construct(
        private readonly GroupWorkoutModel $groupWorkouts,
        private readonly GroupWorkoutParticipantModel $participants,
        private readonly FriendshipModel $friendships,
        private readonly GamificationService $gamification,
        private readonly AchievementService $achievements
    ) {
    }

    public function create(int $creatorId, array $data): array
    {
        if (empty($data['name']) || empty($data['scheduled_date'])) {
            throw new InvalidArgumentException('name y scheduled_date son requeridos');
        }

        $friendIds = $this->friendships->idsForUser($creatorId);
        $inviteeIds = array_values(array_unique(array_map('intval', $data['participant_ids'] ?? [])));
        foreach ($inviteeIds as $id) {
            if (!in_array($id, $friendIds, true)) {
                throw new InvalidArgumentException('Solo puedes invitar a amigos');
            }
        }

        $id = $this->groupWorkouts->create($creatorId, $data);
        $this->participants->add($id, $creatorId, 'accepted');
        foreach ($inviteeIds as $inviteeId) {
            $this->participants->add($id, $inviteeId, 'invited');
        }

        return $this->get($creatorId, $id);
    }

    public function get(int $userId, int $id): array
    {
        $groupWorkout = $this->groupWorkouts->findById($id);
        if ($groupWorkout === null || $this->participants->findOne($id, $userId) === null) {
            throw new InvalidArgumentException('Entrenamiento grupal no encontrado');
        }
        $groupWorkout['participants'] = $this->participants->findForGroupWorkout($id);
        return $groupWorkout;
    }

    public function list(int $userId): array
    {
        return array_map(function ($gw) {
            $gw['participants'] = $this->participants->findForGroupWorkout((int) $gw['id']);
            return $gw;
        }, $this->groupWorkouts->findForUser($userId));
    }

    public function invite(int $userId, int $groupWorkoutId, int $inviteeId): void
    {
        $groupWorkout = $this->groupWorkouts->findById($groupWorkoutId);
        if ($groupWorkout === null || (int) $groupWorkout['creator_id'] !== $userId) {
            throw new InvalidArgumentException('Solo el creador puede invitar participantes');
        }
        if (!in_array($inviteeId, $this->friendships->idsForUser($userId), true)) {
            throw new InvalidArgumentException('Solo puedes invitar a amigos');
        }
        if ($this->participants->findOne($groupWorkoutId, $inviteeId) !== null) {
            throw new InvalidArgumentException('Ese usuario ya fue invitado');
        }
        $this->participants->add($groupWorkoutId, $inviteeId, 'invited');
    }

    public function respond(int $userId, int $groupWorkoutId, bool $accept): array
    {
        if ($this->participants->findOne($groupWorkoutId, $userId) === null) {
            throw new InvalidArgumentException('No fuiste invitado a este entrenamiento');
        }
        $this->participants->updateStatus($groupWorkoutId, $userId, $accept ? 'accepted' : 'declined');
        return ['group_workout_id' => $groupWorkoutId, 'status' => $accept ? 'accepted' : 'declined'];
    }

    /** Solo el creador puede finalizarlo; otorga XP de "entrenamiento con amigos" a los asistentes. */
    public function complete(int $userId, int $groupWorkoutId): array
    {
        $groupWorkout = $this->groupWorkouts->findById($groupWorkoutId);
        if ($groupWorkout === null || (int) $groupWorkout['creator_id'] !== $userId) {
            throw new InvalidArgumentException('Solo el creador puede finalizar el entrenamiento');
        }

        $this->groupWorkouts->setStatus($groupWorkoutId, 'completed');

        foreach ($this->participants->findForGroupWorkout($groupWorkoutId) as $p) {
            if ($p['status'] !== 'accepted') {
                continue;
            }
            $participantId = (int) $p['user_id'];
            $this->gamification->awardXp($participantId, 'group_workout', "group_workout:{$groupWorkoutId}", 'Entrenamiento con amigos: ' . $groupWorkout['name']);
            $this->achievements->evaluate($participantId);
        }

        return $this->get($userId, $groupWorkoutId);
    }
}
