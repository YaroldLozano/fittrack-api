<?php

namespace App\Services;

use App\Core\PublicProfile;
use App\Models\BlockedUserModel;
use App\Models\ExerciseProgressModel;
use App\Models\FriendRequestModel;
use App\Models\FriendshipModel;
use App\Models\UserAchievementModel;
use App\Models\UserModel;
use App\Models\UserStatsModel;
use App\Models\WorkoutSessionModel;
use App\Models\WorkoutSetModel;
use InvalidArgumentException;

class FriendService
{
    public function __construct(
        private readonly UserModel $users,
        private readonly UserStatsModel $stats,
        private readonly FriendRequestModel $requests,
        private readonly FriendshipModel $friendships,
        private readonly BlockedUserModel $blocked,
        private readonly WorkoutSessionModel $sessions,
        private readonly WorkoutSetModel $sets,
        private readonly ExerciseProgressModel $exerciseProgress,
        private readonly UserAchievementModel $userAchievements,
        private readonly ?NotificationService $notifications = null
    ) {
    }

    /** Perfil público (sección 22/57): visible para cualquier usuario autenticado, nunca incluye email. */
    public function publicProfile(int $viewerId, int $targetId): array
    {
        $target = $this->users->findById($targetId);
        if ($target === null) {
            throw new InvalidArgumentException('Usuario no encontrado');
        }

        $profile = PublicProfile::summary($target, $this->stats->findByUser($targetId));
        $profile['relation'] = $viewerId === $targetId
            ? 'self'
            : $this->relationStatus($viewerId, $targetId, $this->friendships->idsForUser($viewerId));

        $completedWorkouts = count(array_filter(
            $this->sessions->findAllForUser($targetId),
            fn ($s) => $s['status'] === 'completed'
        ));

        $profile['workouts_completed'] = $completedWorkouts;
        $profile['personal_records'] = $this->sets->countPersonalRecords($targetId);
        $profile['achievements_count'] = $this->userAchievements->countForUser($targetId);
        $profile['top_exercises'] = array_map(function ($row) {
            $levelInfo = \App\Config\GamificationRules::levelInfo((int) $row['level'], \App\Config\GamificationRules::EXERCISE_LEVELS);
            return [
                'exercise_id' => (int) $row['exercise_id'],
                'exercise_name' => $row['exercise_name'],
                'level' => (int) $row['level'],
                'level_name' => $levelInfo['name'],
                'badge' => $levelInfo['badge'],
            ];
        }, $this->exerciseProgress->topForUser($targetId, 5));

        return $profile;
    }

    public function search(int $userId, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $friendIds = $this->friendships->idsForUser($userId);

        return array_values(array_filter(array_map(function ($user) use ($userId, $friendIds) {
            $targetId = (int) $user['id'];
            if ($this->blocked->isBlockedEitherWay($userId, $targetId)) {
                return null;
            }

            $profile = PublicProfile::summary($user, $this->stats->findByUser($targetId));
            $profile['relation'] = $this->relationStatus($userId, $targetId, $friendIds);
            return $profile;
        }, $this->users->searchByUsername($query, $userId))));
    }

    public function sendRequest(int $userId, int $targetUserId): array
    {
        if ($userId === $targetUserId) {
            throw new InvalidArgumentException('No puedes agregarte a ti mismo');
        }
        if ($this->users->findById($targetUserId) === null) {
            throw new InvalidArgumentException('Usuario no encontrado');
        }
        if ($this->blocked->isBlockedEitherWay($userId, $targetUserId)) {
            throw new InvalidArgumentException('No es posible enviar la solicitud');
        }
        if ($this->friendships->areFriends($userId, $targetUserId)) {
            throw new InvalidArgumentException('Ya son amigos');
        }
        if ($this->requests->findPendingBetween($userId, $targetUserId) !== null) {
            throw new InvalidArgumentException('Ya existe una solicitud pendiente');
        }

        $id = $this->requests->create($userId, $targetUserId);
        $this->notifications?->notify($targetUserId, 'friend_request', $userId, 'friend_request', $id);
        return ['id' => $id, 'status' => 'pending'];
    }

    public function respondToRequest(int $userId, int $requestId, bool $accept): array
    {
        $request = $this->requests->findById($requestId);
        if ($request === null || (int) $request['addressee_id'] !== $userId) {
            throw new InvalidArgumentException('Solicitud no encontrada');
        }
        if ($request['status'] !== 'pending') {
            throw new InvalidArgumentException('Esta solicitud ya fue respondida');
        }

        $this->requests->updateStatus($requestId, $accept ? 'accepted' : 'rejected');

        if ($accept) {
            $this->friendships->create((int) $request['requester_id'], $userId);
        }

        return ['id' => $requestId, 'status' => $accept ? 'accepted' : 'rejected'];
    }

    public function cancelRequest(int $userId, int $requestId): void
    {
        $request = $this->requests->findById($requestId);
        if ($request === null || (int) $request['requester_id'] !== $userId) {
            throw new InvalidArgumentException('Solicitud no encontrada');
        }
        if ($request['status'] !== 'pending') {
            throw new InvalidArgumentException('Esta solicitud ya no está pendiente');
        }

        $this->requests->updateStatus($requestId, 'cancelled');
    }

    public function removeFriend(int $userId, int $friendId): void
    {
        if (!$this->friendships->areFriends($userId, $friendId)) {
            throw new InvalidArgumentException('No son amigos');
        }
        $this->friendships->remove($userId, $friendId);
    }

    public function block(int $userId, int $targetUserId): void
    {
        if ($userId === $targetUserId) {
            throw new InvalidArgumentException('No puedes bloquearte a ti mismo');
        }
        $this->friendships->remove($userId, $targetUserId);
        $this->blocked->block($userId, $targetUserId);
    }

    public function listFriends(int $userId): array
    {
        return array_map(function ($row) {
            $levelInfo = \App\Config\GamificationRules::levelInfo((int) ($row['level'] ?? 1));
            return [
                'id' => (int) $row['id'],
                'username' => $row['username'],
                'name' => $row['name'],
                'avatar_media_id' => $row['avatar_media_id'] !== null ? (int) $row['avatar_media_id'] : null,
                'level' => (int) ($row['level'] ?? 1),
                'level_name' => $levelInfo['name'],
                'badge' => $levelInfo['badge'],
                'xp_total' => (int) ($row['xp_total'] ?? 0),
                'current_streak_days' => (int) ($row['current_streak_days'] ?? 0),
                'friends_since' => $row['friends_since'],
            ];
        }, $this->friendships->listForUser($userId));
    }

    public function listIncomingRequests(int $userId): array
    {
        return $this->requests->findIncomingPending($userId);
    }

    public function listOutgoingRequests(int $userId): array
    {
        return $this->requests->findOutgoingPending($userId);
    }

    /** Comparación lado a lado (sección 23): stats generales + ejercicios compartidos. */
    public function compare(int $viewerId, int $targetId): array
    {
        if ($viewerId === $targetId) {
            throw new InvalidArgumentException('No puedes compararte contigo mismo');
        }

        $viewerProfile = $this->publicProfile($viewerId, $viewerId);
        $targetProfile = $this->publicProfile($viewerId, $targetId);

        $viewerExercises = $this->exerciseProgress->topForUser($viewerId, 50);
        $targetExercises = $this->exerciseProgress->topForUser($targetId, 50);
        $targetByExercise = [];
        foreach ($targetExercises as $row) {
            $targetByExercise[(int) $row['exercise_id']] = $row;
        }

        $exercises = [];
        $viewerWins = 0;
        $targetWins = 0;

        foreach ($viewerExercises as $row) {
            $exerciseId = (int) $row['exercise_id'];
            if (!isset($targetByExercise[$exerciseId])) {
                continue;
            }

            $viewerBest = $this->sets->bestSet($viewerId, $exerciseId);
            $targetBest = $this->sets->bestSet($targetId, $exerciseId);
            $viewerWeight = (float) ($viewerBest['weight'] ?? 0);
            $targetWeight = (float) ($targetBest['weight'] ?? 0);

            if ($viewerWeight > $targetWeight) {
                $viewerWins++;
            } elseif ($targetWeight > $viewerWeight) {
                $targetWins++;
            }

            $exercises[] = [
                'exercise_id' => $exerciseId,
                'exercise_name' => $row['exercise_name'],
                'viewer_weight' => $viewerWeight,
                'target_weight' => $targetWeight,
            ];
        }

        return [
            'viewer' => $viewerProfile,
            'target' => $targetProfile,
            'exercises' => $exercises,
            'viewer_wins' => $viewerWins,
            'target_wins' => $targetWins,
            'summary' => $exercises === []
                ? 'Todavía no hay ejercicios en común para comparar'
                : "Vas ganando en {$viewerWins} de " . count($exercises) . ' ejercicios',
        ];
    }

    private function relationStatus(int $userId, int $targetId, array $friendIds): string
    {
        if (in_array($targetId, $friendIds, true)) {
            return 'friends';
        }

        $pending = $this->requests->findPendingBetween($userId, $targetId);
        if ($pending === null) {
            return 'none';
        }

        return (int) $pending['requester_id'] === $userId ? 'pending_outgoing' : 'pending_incoming';
    }
}
