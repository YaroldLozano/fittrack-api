<?php

namespace App\Services;

use App\Config\GamificationRules;
use App\Models\ExerciseProgressModel;
use App\Models\SeasonModel;
use App\Models\UserStatsModel;
use App\Models\XpTransactionModel;
use PDO;

class GamificationService
{
    public function __construct(
        private readonly PDO $db,
        private readonly XpTransactionModel $xpTransactions,
        private readonly UserStatsModel $stats,
        private readonly SeasonModel $seasons,
        private readonly ExerciseProgressModel $exerciseProgress
    ) {
    }

    /**
     * XP de progresión propia del ejercicio (independiente del XP/nivel global).
     * No pasa por el ledger antifraude: cada set es una fila real única, no reproducible.
     */
    public function onExerciseSetLogged(int $userId, int $exerciseId, bool $isPr): array
    {
        $amount = GamificationRules::EXERCISE_SET_XP + ($isPr ? GamificationRules::EXERCISE_PR_BONUS_XP : 0);

        $before = $this->exerciseProgress->ensureExists($userId, $exerciseId);
        $newTotal = (int) $before['xp'] + $amount;
        $newLevel = GamificationRules::levelForXp($newTotal, GamificationRules::EXERCISE_LEVELS);
        $this->exerciseProgress->addXp($userId, $exerciseId, $amount, $newLevel);

        return [
            'xp_total' => $newTotal,
            'level' => $newLevel,
            'leveled_up' => $newLevel > (int) $before['level'],
        ];
    }

    /**
     * Otorga XP de forma idempotente (protegido por el UNIQUE de xp_transactions).
     * @return array{awarded: bool, xp_total: int, level: int, leveled_up: bool}
     */
    public function awardXp(int $userId, string $source, string $sourceId, ?string $description = null): array
    {
        $amount = GamificationRules::XP[$source] ?? 0;
        if ($amount <= 0) {
            $before = $this->stats->ensureExists($userId);
            return ['awarded' => false, 'xp_total' => (int) $before['xp_total'], 'level' => (int) $before['level'], 'leveled_up' => false];
        }

        $this->db->beginTransaction();
        try {
            $before = $this->stats->ensureExists($userId);
            $inserted = $this->xpTransactions->tryInsert($userId, $amount, $source, $sourceId, $description, $this->getCurrentSeasonId());

            if (!$inserted) {
                $this->db->commit();
                return ['awarded' => false, 'xp_total' => (int) $before['xp_total'], 'level' => (int) $before['level'], 'leveled_up' => false];
            }

            $newTotal = (int) $before['xp_total'] + $amount;
            $newLevel = GamificationRules::levelForXp($newTotal);
            $this->stats->addXp($userId, $amount, $newLevel);
            $this->db->commit();

            return [
                'awarded' => true,
                'xp_total' => $newTotal,
                'level' => $newLevel,
                'leveled_up' => $newLevel > (int) $before['level'],
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Llamado al completar un entrenamiento: otorga XP (con topes antifraude) y actualiza la racha.
     * @return array{xp_awarded: int, leveled_up: bool, level: int, streak: int, streak_milestone: array|null}
     */
    public function onWorkoutCompleted(int $userId, int $sessionId, ?int $durationSeconds): array
    {
        $xpAwarded = 0;
        $leveledUp = false;
        $level = (int) $this->stats->ensureExists($userId)['level'];

        $eligible = $durationSeconds !== null && $durationSeconds >= GamificationRules::MIN_WORKOUT_DURATION_SECONDS
            && $this->xpTransactions->countTodayBySource($userId, 'workout_completed') < GamificationRules::MAX_XP_WORKOUTS_PER_DAY;

        if ($eligible) {
            $result = $this->awardXp($userId, 'workout_completed', (string) $sessionId, 'Entrenamiento completado');
            if ($result['awarded']) {
                $xpAwarded += GamificationRules::XP['workout_completed'];
                $leveledUp = $leveledUp || $result['leveled_up'];
                $level = $result['level'];
            }
        }

        $streakResult = $this->registerActivityStreak($userId);
        if ($streakResult['xp_awarded'] > 0) {
            $xpAwarded += $streakResult['xp_awarded'];
            $leveledUp = $leveledUp || $streakResult['leveled_up'];
            $level = $streakResult['level'];
        }

        return [
            'xp_awarded' => $xpAwarded,
            'leveled_up' => $leveledUp,
            'level' => $level,
            'streak' => $streakResult['streak'],
            'streak_milestone' => $streakResult['milestone'],
        ];
    }

    /** Llamado cuando un set queda marcado como récord personal. */
    public function onPersonalRecord(int $userId, int $setId): array
    {
        return $this->awardXp($userId, 'personal_record', (string) $setId, 'Nuevo récord personal');
    }

    /** Llamado cuando un objetivo pasa a estado completado. */
    public function onGoalCompleted(int $userId, int $goalId): array
    {
        return $this->awardXp($userId, 'goal_completed', (string) $goalId, 'Objetivo completado');
    }

    /**
     * Actualiza la racha de actividad (basada en días con al menos un entrenamiento completado)
     * y otorga XP de racha una única vez por hito y por ocurrencia (no por día repetido).
     */
    private function registerActivityStreak(int $userId): array
    {
        $today = date('Y-m-d');
        $stats = $this->stats->ensureExists($userId);
        $last = $stats['last_activity_date'];

        if ($last === $today) {
            return ['streak' => (int) $stats['current_streak_days'], 'xp_awarded' => 0, 'leveled_up' => false, 'level' => (int) $stats['level'], 'milestone' => null];
        }

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $newStreak = ($last === $yesterday) ? (int) $stats['current_streak_days'] + 1 : 1;
        $newLongest = max($newStreak, (int) $stats['longest_streak_days']);

        $this->stats->updateStreak($userId, $newStreak, $newLongest, $today);

        $milestones = [7 => 'streak_7', 30 => 'streak_30', 90 => 'streak_90', 365 => 'streak_365'];
        $xpAwarded = 0;
        $leveledUp = false;
        $level = (int) $stats['level'];
        $milestoneHit = null;

        if (isset($milestones[$newStreak])) {
            $source = $milestones[$newStreak];
            $result = $this->awardXp($userId, $source, "{$source}:{$today}", "Racha de {$newStreak} días");
            if ($result['awarded']) {
                $xpAwarded = GamificationRules::XP[$source];
                $leveledUp = $result['leveled_up'];
                $level = $result['level'];
                $milestoneHit = ['days' => $newStreak, 'xp' => $xpAwarded];
            }
        }

        return ['streak' => $newStreak, 'xp_awarded' => $xpAwarded, 'leveled_up' => $leveledUp, 'level' => $level, 'milestone' => $milestoneHit];
    }

    /** Temporada activa (mensual), creada de forma perezosa si no existe o si ya venció. */
    public function getCurrentSeasonId(): int
    {
        $active = $this->seasons->findActive();
        $today = date('Y-m-d');

        if ($active !== null && $active['ends_at'] >= $today) {
            return (int) $active['id'];
        }

        if ($active !== null) {
            $this->seasons->close((int) $active['id']);
        }

        $startsAt = date('Y-m-01');
        $endsAt = date('Y-m-t');
        $name = 'Temporada ' . date('m-Y');

        return $this->seasons->create($name, $startsAt, $endsAt);
    }

    public function getMe(int $userId): array
    {
        $stats = $this->stats->ensureExists($userId);
        $xpTotal = (int) $stats['xp_total'];
        $level = (int) $stats['level'];
        $levelInfo = GamificationRules::levelInfo($level);
        $nextLevelInfo = GamificationRules::levelInfo($level + 1);

        $rankStmt = $this->db->prepare('SELECT COUNT(*) + 1 FROM user_stats WHERE xp_total > :xp');
        $rankStmt->execute(['xp' => $xpTotal]);

        return [
            'level' => $level,
            'level_name' => $levelInfo['name'],
            'badge' => $levelInfo['badge'],
            'xp_total' => $xpTotal,
            'xp_to_next_level' => GamificationRules::xpToNextLevel($xpTotal),
            'next_level_name' => $level < 7 ? $nextLevelInfo['name'] : null,
            'current_streak_days' => (int) $stats['current_streak_days'],
            'longest_streak_days' => (int) $stats['longest_streak_days'],
            'rank_by_xp' => (int) $rankStmt->fetchColumn(),
        ];
    }
}
