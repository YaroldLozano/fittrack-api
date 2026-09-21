<?php

namespace App\Services;

use App\Config\GamificationRules;
use App\Core\PublicProfile;
use App\Models\ExerciseModel;
use App\Models\FriendshipModel;
use App\Models\RankingModel;
use App\Models\SeasonModel;
use InvalidArgumentException;

class RankingService
{
    private const SCORE_REFRESH_MINUTES = 15;
    private const GLOBAL_NEARBY_WINDOW = 5;

    public function __construct(
        private readonly RankingModel $ranking,
        private readonly FriendshipModel $friendships,
        private readonly SeasonModel $seasons,
        private readonly ExerciseModel $exercises,
        private readonly GamificationService $gamification
    ) {
    }

    private function refreshIfStale(): void
    {
        $last = $this->ranking->lastRefreshedAt();
        if ($last !== null && strtotime($last) > time() - self::SCORE_REFRESH_MINUTES * 60) {
            return;
        }
        $this->ranking->refreshAllScores(GamificationRules::RANKING_WEIGHTS);
    }

    public function global(int $userId, string $mode, int $page, int $limit): array
    {
        $this->refreshIfStale();
        $limit = max(1, min(100, $limit));

        $rows = $mode === 'nearby'
            ? $this->ranking->globalNearby($userId, self::GLOBAL_NEARBY_WINDOW)
            : $this->ranking->globalPage($limit, max(0, $page - 1) * $limit);

        return [
            'mode' => $mode,
            'page' => $page,
            'limit' => $limit,
            'total' => $this->ranking->totalUsers(),
            'entries' => array_map(fn ($r) => $this->formatRankedRow($r, $userId), $rows),
        ];
    }

    public function friends(int $userId): array
    {
        $ids = array_merge([$userId], $this->friendships->idsForUser($userId));
        $rows = $this->ranking->rankingForUserIds($ids);

        return ['entries' => $this->formatXpOrderedRows($rows, $userId)];
    }

    public function weekly(int $userId, bool $friendsOnly): array
    {
        $weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $weekEnd = date('Y-m-d 00:00:00', strtotime('monday next week'));
        $userIds = $friendsOnly ? array_merge([$userId], $this->friendships->idsForUser($userId)) : null;

        $rows = $this->ranking->xpLeaderboardBetween($weekStart, $weekEnd, $userIds, 50);

        return [
            'period_start' => $weekStart,
            'period_end' => $weekEnd,
            'seconds_remaining' => max(0, strtotime($weekEnd) - time()),
            'entries' => $this->formatPeriodRows($rows, $userId, 'xp_period'),
        ];
    }

    public function season(int $userId, bool $friendsOnly): array
    {
        $seasonId = $this->gamification->getCurrentSeasonId();
        $seasonRow = $this->seasons->findById($seasonId);
        $userIds = $friendsOnly ? array_merge([$userId], $this->friendships->idsForUser($userId)) : null;

        $rows = $this->ranking->xpLeaderboardBetween(
            $seasonRow['starts_at'] . ' 00:00:00',
            date('Y-m-d 00:00:00', strtotime($seasonRow['ends_at'] . ' +1 day')),
            $userIds,
            50
        );

        return [
            'season' => ['id' => $seasonId, 'name' => $seasonRow['name'], 'starts_at' => $seasonRow['starts_at'], 'ends_at' => $seasonRow['ends_at']],
            'entries' => $this->formatPeriodRows($rows, $userId, 'xp_period'),
        ];
    }

    public function byExercise(int $userId, int $exerciseId, string $scope): array
    {
        if ($this->exercises->findVisibleById($userId, $exerciseId) === null) {
            throw new InvalidArgumentException('Ejercicio no encontrado');
        }

        $userIds = $scope === 'friends' ? array_merge([$userId], $this->friendships->idsForUser($userId)) : null;
        $rows = $this->ranking->exerciseLeaderboard($exerciseId, $userIds, 50);

        return [
            'exercise_id' => $exerciseId,
            'scope' => $scope,
            'entries' => array_map(function ($r) use ($userId) {
                return [
                    'id' => (int) $r['id'],
                    'username' => $r['username'],
                    'name' => $r['name'],
                    'best_weight' => $r['best_weight'] !== null ? (float) $r['best_weight'] : null,
                    'reps_at_best' => $r['reps_at_best'] !== null ? (int) $r['reps_at_best'] : null,
                    'is_me' => (int) $r['id'] === $userId,
                ];
            }, $rows),
        ];
    }

    private function formatRankedRow(array $r, int $viewerId): array
    {
        $stats = ['level' => $r['level'], 'xp_total' => $r['xp_total'], 'current_streak_days' => $r['current_streak_days']];
        $profile = PublicProfile::summary($r, $stats);
        $profile['rank'] = (int) $r['rank_position'];
        $profile['rank_change'] = $r['previous_rank_position'] !== null
            ? (int) $r['previous_rank_position'] - (int) $r['rank_position']
            : null;
        $profile['ranking_score'] = (float) $r['ranking_score'];
        $profile['is_me'] = (int) $r['id'] === $viewerId;
        return $profile;
    }

    private function formatXpOrderedRows(array $rows, int $viewerId): array
    {
        return array_values(array_map(function ($r, $i) use ($viewerId) {
            $stats = ['level' => $r['level'], 'xp_total' => $r['xp_total'], 'current_streak_days' => $r['current_streak_days']];
            $profile = PublicProfile::summary($r, $stats);
            $profile['rank'] = $i + 1;
            $profile['is_me'] = (int) $r['id'] === $viewerId;
            return $profile;
        }, $rows, array_keys($rows)));
    }

    private function formatPeriodRows(array $rows, int $viewerId, string $amountKey): array
    {
        return array_values(array_map(function ($r, $i) use ($viewerId, $amountKey) {
            return [
                'id' => (int) $r['id'],
                'username' => $r['username'],
                'name' => $r['name'],
                'rank' => $i + 1,
                'xp_period' => (int) $r[$amountKey],
                'is_me' => (int) $r['id'] === $viewerId,
            ];
        }, $rows, array_keys($rows)));
    }
}
