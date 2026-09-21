<?php

namespace App\Models;

use PDO;

class RankingModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function lastRefreshedAt(): ?string
    {
        $stmt = $this->db->query('SELECT last_refreshed_at FROM ranking_meta WHERE id = 1');
        $value = $stmt->fetchColumn();
        return $value ?: null;
    }

    public function markRefreshed(): void
    {
        $this->db->exec('UPDATE ranking_meta SET last_refreshed_at = NOW() WHERE id = 1');
    }

    /**
     * Recalcula ranking_score para todos los usuarios en un solo pase, normalizando cada
     * componente por percentil (PERCENT_RANK) antes de aplicar los pesos de GamificationRules.
     * También desplaza rank_position -> previous_rank_position antes de recalcular las posiciones.
     */
    public function refreshAllScores(array $weights): void
    {
        $this->db->exec('UPDATE user_stats SET previous_rank_position = rank_position');

        // Nota: MariaDB no permite que una tabla derivada (subconsulta en FROM) se correlacione
        // con la fila de la consulta externa — por eso cada métrica se calcula en su propio CTE
        // agrupado por user_id (sin correlación) y luego se unen todos con LEFT JOIN.
        $stmt = $this->db->prepare(
            "WITH progress_cte AS (
                SELECT user_id, MAX(pct) AS progress_raw FROM (
                    SELECT user_id, exercise_id, ((MAX(weight) - MIN(weight)) / NULLIF(MIN(weight), 0)) * 100 AS pct
                    FROM workout_sets
                    GROUP BY user_id, exercise_id
                    HAVING COUNT(*) >= 2
                ) t
                GROUP BY user_id
            ),
            consistency_cte AS (
                SELECT user_id, COUNT(*) AS consistency_raw
                FROM workout_sessions
                WHERE status = 'completed' AND completed_at >= NOW() - INTERVAL 30 DAY
                GROUP BY user_id
            ),
            best_weight_cte AS (
                SELECT user_id, MAX(weight) AS max_weight FROM workout_sets GROUP BY user_id
            ),
            latest_bodyweight_cte AS (
                SELECT user_id, weight FROM body_metrics bm1
                WHERE recorded_date = (SELECT MAX(recorded_date) FROM body_metrics bm2 WHERE bm2.user_id = bm1.user_id)
            ),
            volume_cte AS (
                SELECT user_id, SUM(weight * reps) AS volume_raw
                FROM workout_sets WHERE completed_at >= NOW() - INTERVAL 30 DAY
                GROUP BY user_id
            ),
            achievements_cte AS (
                SELECT user_id, COUNT(*) AS achievements_raw FROM user_achievements GROUP BY user_id
            ),
            raw_metrics AS (
                SELECT u.id AS user_id,
                    COALESCE(p.progress_raw, 0) AS progress_raw,
                    COALESCE(c.consistency_raw, 0) AS consistency_raw,
                    COALESCE(bw.max_weight / NULLIF(lb.weight, 0), 0) AS strength_raw,
                    COALESCE(v.volume_raw, 0) AS volume_raw,
                    COALESCE(a.achievements_raw, 0) AS achievements_raw
                FROM users u
                LEFT JOIN progress_cte p ON p.user_id = u.id
                LEFT JOIN consistency_cte c ON c.user_id = u.id
                LEFT JOIN best_weight_cte bw ON bw.user_id = u.id
                LEFT JOIN latest_bodyweight_cte lb ON lb.user_id = u.id
                LEFT JOIN volume_cte v ON v.user_id = u.id
                LEFT JOIN achievements_cte a ON a.user_id = u.id
            ),
            percentiles AS (
                SELECT user_id,
                    PERCENT_RANK() OVER (ORDER BY progress_raw) AS progress_pct,
                    PERCENT_RANK() OVER (ORDER BY consistency_raw) AS consistency_pct,
                    PERCENT_RANK() OVER (ORDER BY strength_raw) AS strength_pct,
                    PERCENT_RANK() OVER (ORDER BY volume_raw) AS volume_pct,
                    PERCENT_RANK() OVER (ORDER BY achievements_raw) AS achievements_pct
                FROM raw_metrics
            )
            SELECT user_id,
                (progress_pct * :w_progress + consistency_pct * :w_consistency + strength_pct * :w_strength
                 + volume_pct * :w_volume + achievements_pct * :w_achievements) * 100 AS score
            FROM percentiles"
        );
        $stmt->execute([
            'w_progress' => $weights['progress'],
            'w_consistency' => $weights['consistency'],
            'w_strength' => $weights['relative_strength'],
            'w_volume' => $weights['volume'],
            'w_achievements' => $weights['achievements'],
        ]);
        $scores = $stmt->fetchAll();

        $update = $this->db->prepare('UPDATE user_stats SET ranking_score = :score, last_score_refresh_at = NOW() WHERE user_id = :user_id');
        foreach ($scores as $row) {
            $update->execute(['score' => round((float) $row['score'], 2), 'user_id' => $row['user_id']]);
        }

        // Recalcular posiciones globales tras actualizar los scores.
        $this->db->exec(
            'UPDATE user_stats us
             INNER JOIN (
                 SELECT user_id, RANK() OVER (ORDER BY ranking_score DESC) AS rnk FROM user_stats
             ) r ON r.user_id = us.user_id
             SET us.rank_position = r.rnk'
        );

        $this->markRefreshed();
    }

    public function globalPage(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.username, u.name, u.avatar_media_id, s.level, s.xp_total, s.current_streak_days,
                    s.ranking_score, s.rank_position, s.previous_rank_position
             FROM user_stats s
             INNER JOIN users u ON u.id = s.user_id
             ORDER BY s.ranking_score DESC, s.user_id ASC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function totalUsers(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM user_stats');
        return (int) $stmt->fetchColumn();
    }

    /** Ventana de usuarios alrededor de la posición del usuario dado (sección 11: "cercanos a mí"). */
    public function globalNearby(int $userId, int $window): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.username, u.name, u.avatar_media_id, s.level, s.xp_total, s.current_streak_days,
                    s.ranking_score, s.rank_position, s.previous_rank_position
             FROM user_stats s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.rank_position BETWEEN
                (SELECT rank_position FROM user_stats WHERE user_id = :user_id) - :window
                AND (SELECT rank_position FROM user_stats WHERE user_id = :user_id2) + :window2
             ORDER BY s.rank_position ASC'
        );
        $stmt->execute(['user_id' => $userId, 'window' => $window, 'user_id2' => $userId, 'window2' => $window]);
        return $stmt->fetchAll();
    }

    /** Ranking entre un conjunto de usuarios (amigos + uno mismo). */
    public function rankingForUserIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, u.name, u.avatar_media_id, s.level, s.xp_total, s.current_streak_days, s.ranking_score
             FROM user_stats s
             INNER JOIN users u ON u.id = s.user_id
             WHERE u.id IN ($placeholders)
             ORDER BY s.ranking_score DESC, s.user_id ASC"
        );
        $stmt->execute(array_values($userIds));
        return $stmt->fetchAll();
    }

    /** Ranking por XP ganada entre fechas (semanal) o por season_id (temporada). PDO no permite mezclar
     *  parámetros nombrados y posicionales en una misma consulta, así que el IN(...) usa nombrados también. */
    public function xpLeaderboardBetween(string $from, string $to, ?array $userIds, int $limit): array
    {
        $sql = "SELECT u.id, u.username, u.name, COALESCE(SUM(x.amount), 0) AS xp_period
                FROM users u
                LEFT JOIN xp_transactions x ON x.user_id = u.id AND x.created_at >= :from AND x.created_at < :to";
        $params = ['from' => $from, 'to' => $to];

        if ($userIds !== null) {
            if (empty($userIds)) {
                return [];
            }
            [$inClause, $inParams] = $this->namedInClause('uid', $userIds);
            $sql .= " WHERE u.id IN ($inClause)";
            $params = array_merge($params, $inParams);
        }

        $sql .= ' GROUP BY u.id, u.username, u.name ORDER BY xp_period DESC, u.id ASC LIMIT ' . (int) $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Ranking por ejercicio: mejor peso levantado, entre un conjunto de usuarios o global. */
    public function exerciseLeaderboard(int $exerciseId, ?array $userIds, int $limit): array
    {
        $sql = "SELECT u.id, u.username, u.name, MAX(ws.weight) AS best_weight, MAX(ws.reps) AS reps_at_best
                FROM workout_sets ws
                INNER JOIN users u ON u.id = ws.user_id
                WHERE ws.exercise_id = :exercise_id";
        $params = ['exercise_id' => $exerciseId];

        if ($userIds !== null) {
            if (empty($userIds)) {
                return [];
            }
            [$inClause, $inParams] = $this->namedInClause('uid', $userIds);
            $sql .= " AND u.id IN ($inClause)";
            $params = array_merge($params, $inParams);
        }

        $sql .= ' GROUP BY u.id, u.username, u.name ORDER BY best_weight DESC LIMIT ' . (int) $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array{0: string, 1: array<string,int>} */
    private function namedInClause(string $prefix, array $values): array
    {
        $names = [];
        $params = [];
        foreach (array_values($values) as $i => $value) {
            $name = "{$prefix}{$i}";
            $names[] = ":{$name}";
            $params[$name] = $value;
        }
        return [implode(',', $names), $params];
    }
}
