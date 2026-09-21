<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\GoalService;
use InvalidArgumentException;

class GoalController
{
    public function __construct(private readonly GoalService $goals)
    {
    }

    public function index(Request $request): void
    {
        Response::success(['goals' => $this->goals->list((int) $request->userId)]);
    }

    public function store(Request $request): void
    {
        try {
            $goal = $this->goals->create((int) $request->userId, $request->body);
            Response::success(['goal' => $goal], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function update(Request $request): void
    {
        try {
            $goal = $this->goals->update((int) $request->userId, (int) $request->params['id'], $request->body);
            Response::success(['goal' => $goal]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function destroy(Request $request): void
    {
        try {
            $this->goals->delete((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Objetivo eliminado']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }
}
