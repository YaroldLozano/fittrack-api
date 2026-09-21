<?php

namespace App\Services;

use App\Models\BodyMetricModel;
use InvalidArgumentException;

class BodyMetricService
{
    public function __construct(
        private readonly BodyMetricModel $bodyMetrics,
        private readonly GoalService $goalService
    ) {
    }

    public function list(int $userId): array
    {
        return $this->bodyMetrics->findAllForUser($userId);
    }

    public function upsert(int $userId, array $data): array
    {
        if (empty($data['recorded_date'])) {
            throw new InvalidArgumentException('recorded_date es requerido');
        }

        $this->bodyMetrics->upsert($userId, $data);

        if (isset($data['weight']) && $data['weight'] !== null) {
            $this->goalService->onBodyWeightLogged($userId, (float) $data['weight']);
        }

        return $this->bodyMetrics->latestForUser($userId);
    }
}
