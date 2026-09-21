<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\GamificationService;
use App\Services\RankingService;
use InvalidArgumentException;

class RankingController
{
    public function __construct(
        private readonly GamificationService $gamification,
        private readonly RankingService $ranking
    ) {
    }

    public function me(Request $request): void
    {
        Response::success(['me' => $this->gamification->getMe((int) $request->userId)]);
    }

    public function global(Request $request): void
    {
        $mode = (string) $request->input('mode', 'page'); // 'page' | 'nearby'
        $page = max(1, (int) $request->input('page', 1));
        $limit = (int) $request->input('limit', 20);
        Response::success(['ranking' => $this->ranking->global((int) $request->userId, $mode, $page, $limit)]);
    }

    public function friends(Request $request): void
    {
        Response::success(['ranking' => $this->ranking->friends((int) $request->userId)]);
    }

    public function weekly(Request $request): void
    {
        $friendsOnly = (string) $request->input('scope', 'global') === 'friends';
        Response::success(['ranking' => $this->ranking->weekly((int) $request->userId, $friendsOnly)]);
    }

    public function season(Request $request): void
    {
        $friendsOnly = (string) $request->input('scope', 'global') === 'friends';
        Response::success(['ranking' => $this->ranking->season((int) $request->userId, $friendsOnly)]);
    }

    public function exercise(Request $request): void
    {
        try {
            $scope = (string) $request->input('scope', 'global');
            $result = $this->ranking->byExercise((int) $request->userId, (int) $request->params['id'], $scope);
            Response::success(['ranking' => $result]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }
}
