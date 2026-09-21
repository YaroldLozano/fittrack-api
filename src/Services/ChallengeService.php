<?php

namespace App\Services;

use App\Models\ChallengeModel;
use App\Models\ChallengeParticipantModel;
use App\Models\FriendshipModel;
use App\Models\UserStatsModel;
use App\Models\WorkoutSessionModel;
use App\Models\WorkoutSetModel;
use InvalidArgumentException;

class ChallengeService
{
    private const TYPES = ['strength', 'volume', 'progress', 'consistency', 'streak'];

    public function __construct(
        private readonly ChallengeModel $challenges,
        private readonly ChallengeParticipantModel $participants,
        private readonly FriendshipModel $friendships,
        private readonly WorkoutSetModel $sets,
        private readonly WorkoutSessionModel $sessions,
        private readonly UserStatsModel $stats,
        private readonly GamificationService $gamification,
        private readonly AchievementService $achievements
    ) {
    }

    public function create(int $creatorId, array $data): array
    {
        if (empty($data['title']) || empty($data['type']) || !in_array($data['type'], self::TYPES, true)) {
            throw new InvalidArgumentException('title y type (válido) son requeridos');
        }
        if (empty($data['starts_at']) || empty($data['ends_at'])) {
            throw new InvalidArgumentException('starts_at y ends_at son requeridos');
        }
        if (in_array($data['type'], ['strength', 'volume', 'progress'], true) && empty($data['exercise_id'])) {
            throw new InvalidArgumentException('Este tipo de reto requiere exercise_id');
        }

        $friendIds = $this->friendships->idsForUser($creatorId);
        $inviteeIds = array_values(array_unique(array_map('intval', $data['participant_ids'] ?? [])));
        foreach ($inviteeIds as $id) {
            if (!in_array($id, $friendIds, true)) {
                throw new InvalidArgumentException('Solo puedes invitar a amigos al reto');
            }
        }

        $challengeId = $this->challenges->create($creatorId, $data);
        $this->participants->add($challengeId, $creatorId, 'accepted');
        foreach ($inviteeIds as $id) {
            $this->participants->add($challengeId, $id, 'invited');
        }

        return $this->get($creatorId, $challengeId);
    }

    public function get(int $userId, int $challengeId): array
    {
        $challenge = $this->challenges->findById($challengeId);
        if ($challenge === null || $this->participants->findOne($challengeId, $userId) === null) {
            throw new InvalidArgumentException('Reto no encontrado');
        }

        $this->refreshProgress($challenge);
        $challenge['participants'] = $this->participants->findForChallenge($challengeId);
        return $challenge;
    }

    public function list(int $userId): array
    {
        $this->closeExpired();
        return array_map(function ($c) {
            $this->refreshProgress($c);
            $c['participants'] = $this->participants->findForChallenge((int) $c['id']);
            return $c;
        }, $this->challenges->findForUser($userId));
    }

    public function respond(int $userId, int $challengeId, bool $accept): array
    {
        $participant = $this->participants->findOne($challengeId, $userId);
        if ($participant === null) {
            throw new InvalidArgumentException('No fuiste invitado a este reto');
        }
        $this->participants->updateStatus($challengeId, $userId, $accept ? 'accepted' : 'declined');
        return ['challenge_id' => $challengeId, 'status' => $accept ? 'accepted' : 'declined'];
    }

    /** Recalcula el progreso de cada participante activo según el tipo de reto. */
    private function refreshProgress(array $challenge): void
    {
        $challengeId = (int) $challenge['id'];
        $exerciseId = $challenge['exercise_id'] !== null ? (int) $challenge['exercise_id'] : null;

        foreach ($this->participants->findForChallenge($challengeId) as $p) {
            if ($p['status'] !== 'accepted') {
                continue;
            }
            $userId = (int) $p['user_id'];

            $value = match ($challenge['type']) {
                'strength' => $exerciseId !== null ? (float) ($this->sets->statsForExercise($userId, $exerciseId)['max_weight'] ?? 0) : 0,
                'volume' => $exerciseId !== null ? (float) ($this->sets->statsForExercise($userId, $exerciseId)['total_volume'] ?? 0) : 0,
                'progress' => $exerciseId !== null ? (float) ($this->exerciseProgressPercent($userId, $exerciseId)) : 0,
                'consistency' => (float) count(array_filter(
                    $this->sessions->findAllForUser($userId, $challenge['starts_at'], $challenge['ends_at']),
                    fn ($s) => $s['status'] === 'completed'
                )),
                'streak' => (float) ($this->stats->findByUser($userId)['current_streak_days'] ?? 0),
                default => 0,
            };

            $this->participants->updateProgress($challengeId, $userId, $value);
        }
    }

    private function exerciseProgressPercent(int $userId, int $exerciseId): float
    {
        $stats = $this->sets->statsForExercise($userId, $exerciseId);
        return (float) ($stats['max_weight'] ?? 0);
    }

    /** Cierra (perezosamente) los retos activos cuya fecha de fin ya pasó, otorga XP y evalúa logros. */
    private function closeExpired(): void
    {
        $this->challenges->activatePending();

        foreach ($this->challenges->findActiveExpired() as $challenge) {
            $challengeId = (int) $challenge['id'];
            $this->refreshProgress($challenge);

            $accepted = array_values(array_filter(
                $this->participants->findForChallenge($challengeId),
                fn ($p) => $p['status'] === 'accepted'
            ));

            $winnerId = null;
            $bestValue = -INF;
            foreach ($accepted as $p) {
                if ((float) $p['progress_value'] > $bestValue) {
                    $bestValue = (float) $p['progress_value'];
                    $winnerId = (int) $p['user_id'];
                }
            }

            $this->challenges->setStatus($challengeId, 'completed', $winnerId);

            foreach ($accepted as $p) {
                $userId = (int) $p['user_id'];
                $this->gamification->awardXp($userId, 'challenge_completed', "challenge:{$challengeId}", 'Reto completado: ' . $challenge['title']);
                if ($userId === $winnerId) {
                    $this->gamification->awardXp($userId, 'challenge_won', "challenge:{$challengeId}", 'Reto ganado: ' . $challenge['title']);
                }
                $this->achievements->evaluate($userId);
            }
        }
    }
}
