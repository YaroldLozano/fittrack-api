<?php

namespace App\Config;

/**
 * Valores de XP, niveles y topes anti-farming, centralizados para poder
 * ajustarlos sin tocar lógica de negocio (ver plan de gamificación).
 */
class GamificationRules
{
    /** @var array<string, int> */
    public const XP = [
        'workout_completed' => 100,
        'goal_completed' => 150,
        'personal_record' => 100,
        'streak_7' => 200,
        'streak_30' => 500,
        'streak_90' => 1000,
        'streak_365' => 2000,
        'challenge_completed' => 300,
        'challenge_won' => 500,
        'group_workout' => 75,
        'achievement_unlocked' => 50,
    ];

    /** Niveles: umbral mínimo de XP acumulada para alcanzarlo. Ordenados ascendente. */
    public const LEVELS = [
        1 => ['name' => 'Principiante', 'badge' => '🌱', 'xp' => 0],
        2 => ['name' => 'Novato', 'badge' => '🥉', 'xp' => 500],
        3 => ['name' => 'Avanzado', 'badge' => '🥈', 'xp' => 1500],
        4 => ['name' => 'Experto', 'badge' => '🥇', 'xp' => 3500],
        5 => ['name' => 'Élite', 'badge' => '💎', 'xp' => 7000],
        6 => ['name' => 'Maestro', 'badge' => '👑', 'xp' => 13000],
        7 => ['name' => 'Leyenda', 'badge' => '🏆', 'xp' => 22000],
    ];

    /** Pesos del Ranking Score (deben sumar 1.0) — cada componente se normaliza por percentil antes de aplicarlos. */
    public const RANKING_WEIGHTS = [
        'progress' => 0.30,
        'consistency' => 0.25,
        'relative_strength' => 0.20,
        'volume' => 0.15,
        'achievements' => 0.10,
    ];

    /** Máximo de entrenamientos que otorgan XP por día (evita farming de sesiones vacías). */
    public const MAX_XP_WORKOUTS_PER_DAY = 3;

    /** Duración mínima (segundos) para que un entrenamiento cuente para XP. */
    public const MIN_WORKOUT_DURATION_SECONDS = 60;

    /** Progresión por ejercicio: curva más corta que la global (sección 19 del spec). Mismas medallas. */
    public const EXERCISE_LEVELS = [
        1 => ['name' => 'Principiante', 'badge' => '🌱', 'xp' => 0],
        2 => ['name' => 'Novato', 'badge' => '🥉', 'xp' => 150],
        3 => ['name' => 'Avanzado', 'badge' => '🥈', 'xp' => 400],
        4 => ['name' => 'Experto', 'badge' => '🥇', 'xp' => 900],
        5 => ['name' => 'Élite', 'badge' => '💎', 'xp' => 1800],
        6 => ['name' => 'Maestro', 'badge' => '👑', 'xp' => 3200],
        7 => ['name' => 'Leyenda', 'badge' => '🏆', 'xp' => 5500],
    ];

    public const EXERCISE_SET_XP = 10;
    public const EXERCISE_PR_BONUS_XP = 40;

    public static function levelForXp(int $xpTotal, array $levels = self::LEVELS): int
    {
        $level = 1;
        foreach ($levels as $lvl => $data) {
            if ($xpTotal >= $data['xp']) {
                $level = $lvl;
            }
        }
        return $level;
    }

    public static function levelInfo(int $level, array $levels = self::LEVELS): array
    {
        return $levels[$level] ?? $levels[1];
    }

    /** XP restante hasta el siguiente nivel, o null si ya está en el máximo. */
    public static function xpToNextLevel(int $xpTotal, array $levels = self::LEVELS): ?int
    {
        $level = self::levelForXp($xpTotal, $levels);
        $next = $levels[$level + 1] ?? null;
        return $next !== null ? max(0, $next['xp'] - $xpTotal) : null;
    }
}
