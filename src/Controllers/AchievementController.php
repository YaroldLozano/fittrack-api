<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AchievementService;

class AchievementController
{
    public function __construct(private readonly AchievementService $achievements)
    {
    }

    public function index(Request $request): void
    {
        Response::success(['achievements' => $this->achievements->listAll()]);
    }

    public function me(Request $request): void
    {
        Response::success($this->achievements->listForUser((int) $request->userId));
    }
}
