<?php

namespace App\Services;

use App\Models\AchievementModel;
use App\Models\ChallengeParticipantModel;
use App\Models\ExerciseProgressModel;
use App\Models\FriendshipModel;
use App\Models\GroupWorkoutParticipantModel;
use App\Models\UserAchievementModel;
use App\Models\UserStatsModel;
use App\Models\WorkoutSetModel;

/**
 * Evalúa el catálogo de logros contra los contadores actuales del usuario y desbloquea
 * los que correspondan. Los requisitos viven en la tabla `achievements` (requirement_type/
 * requirement_value), no hardcodeados aquí — agregar un logro nuevo es solo un INSERT.
 *
 * Los contadores de tipos "social"/"competencia" (amigos, retos, entrenamientos grupales)
 * se agregan cuando esas features existan (oleadas 4 y 6); mientras tanto simplemente no
 * se evalúan (no se desbloquean, no da error).
 */
class AchievementService
{
    public function __construct(
        private readonly AchievementModel $achievements,
        private readonly UserAchievementModel $userAchievements,
        private readonly WorkoutSetModel $sets,
        private readonly ExerciseProgressModel $exerciseProgress,
        private readonly UserStatsModel $stats,
        private readonly GamificationService $gamification,
        private readonly ?FriendshipModel $friendships = null,
        private readonly ?GroupWorkoutParticipantModel $groupWorkoutParticipants = null,
        private readonly ?ChallengeParticipantModel $challengeParticipants = null
    ) {
    }

    public function listAll(): array
    {
        return $this->achievements->all();
    }

    public function listForUser(int $userId): array
    {
        $this->evaluate($userId); // retroactivo: desbloquea lo que ya se cumplía antes de esta consulta
        $unlocked = $this->userAchievements->findForUser($userId);
        $unlockedIds = array_map(fn ($row) => (int) $row['achievement_id'], $unlocked);
        $counters = $this->getCounters($userId);

        $locked = array_filter($this->achievements->all(), fn ($a) => !in_array((int) $a['id'], $unlockedIds, true));
        $lockedWithProgress = array_map(function ($a) use ($counters) {
            $current = $counters[$a['requirement_type']] ?? null;
            $a['current_value'] = $current;
            $a['unlocked'] = false;
            return $a;
        }, $locked);

        $unlockedFormatted = array_map(function ($row) {
            $row['unlocked'] = true;
            return $row;
        }, $unlocked);

        return ['unlocked' => array_values($unlockedFormatted), 'locked' => array_values($lockedWithProgress)];
    }

    /** @return array<int, array> logros recién desbloqueados en esta llamada (con su info + XP otorgado) */
    public function evaluate(int $userId): array
    {
        $counters = $this->getCounters($userId);
        $unlockedIds = $this->userAchievements->unlockedIdsForUser($userId);
        $newlyUnlocked = [];

        foreach ($this->achievements->all() as $achievement) {
            $id = (int) $achievement['id'];
            if (in_array($id, $unlockedIds, true)) {
                continue;
            }

            $current = $counters[$achievement['requirement_type']] ?? null;
            if ($current === null || $current < (float) $achievement['requirement_value']) {
                continue;
            }

            if ($this->userAchievements->tryUnlock($userId, $id)) {
                $this->gamification->awardXp($userId, 'achievement_unlocked', (string) $id, 'Logro: ' . $achievement['name']);
                $newlyUnlocked[] = $achievement;
            }
        }

        return $newlyUnlocked;
    }

    /** @return array<string, float> valor actual por requirement_type disponible hoy */
    private function getCounters(int $userId): array
    {
        $stats = $this->stats->ensureExists($userId);
        $counters = [
            'first_pr' => (float) $this->sets->countPersonalRecords($userId),
            'pr_weight' => (float) ($this->sets->maxWeightEver($userId) ?? 0),
            'exercise_level' => (float) $this->exerciseProgress->maxLevelForUser($userId),
            'streak_days' => (float) $stats['longest_streak_days'],
            'progress_percent' => (float) ($this->sets->bestProgressPercent($userId) ?? 0),
        ];

        if ($this->friendships !== null) {
            $counters['friends_count'] = (float) $this->friendships->countForUser($userId);
        }
        if ($this->groupWorkoutParticipants !== null) {
            $counters['group_workouts_count'] = (float) $this->groupWorkoutParticipants->countCompletedForUser($userId);
        }
        if ($this->challengeParticipants !== null) {
            $counters['challenges_participated'] = (float) $this->challengeParticipants->countAcceptedParticipations($userId);
            $counters['challenges_won'] = (float) $this->challengeParticipants->countWins($userId);
        }

        return $counters;
    }
}
