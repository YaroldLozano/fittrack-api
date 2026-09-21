<?php

namespace App\Core;

use App\Config\GamificationRules;

/**
 * Único punto de control para lo que se expone del perfil de OTRO usuario
 * (búsqueda, amigos, ranking, comparación). Nunca incluye email ni datos internos.
 */
class PublicProfile
{
    public static function summary(array $user, ?array $stats): array
    {
        $level = (int) ($stats['level'] ?? 1);
        $levelInfo = GamificationRules::levelInfo($level);

        return [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'name' => $user['name'] ?? null,
            'bio' => $user['bio'] ?? null,
            'avatar_media_id' => isset($user['avatar_media_id']) ? (int) $user['avatar_media_id'] : null,
            'level' => $level,
            'level_name' => $levelInfo['name'],
            'badge' => $levelInfo['badge'],
            'xp_total' => (int) ($stats['xp_total'] ?? 0),
            'current_streak_days' => (int) ($stats['current_streak_days'] ?? 0),
        ];
    }
}
