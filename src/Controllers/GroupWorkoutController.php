<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\GroupWorkoutService;
use InvalidArgumentException;

class GroupWorkoutController
{
    public function __construct(private readonly GroupWorkoutService $groupWorkouts)
    {
    }

    public function index(Request $request): void
    {
        Response::success(['group_workouts' => $this->groupWorkouts->list((int) $request->userId)]);
    }

    public function store(Request $request): void
    {
        try {
            $gw = $this->groupWorkouts->create((int) $request->userId, $request->body);
            Response::success(['group_workout' => $gw], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function invite(Request $request): void
    {
        try {
            $this->groupWorkouts->invite((int) $request->userId, (int) $request->params['id'], (int) $request->input('user_id', 0));
            Response::success(['message' => 'Invitación enviada']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function respond(Request $request): void
    {
        try {
            $accept = (bool) $request->input('accept', false);
            $result = $this->groupWorkouts->respond((int) $request->userId, (int) $request->params['id'], $accept);
            Response::success(['group_workout' => $result]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function complete(Request $request): void
    {
        try {
            $result = $this->groupWorkouts->complete((int) $request->userId, (int) $request->params['id']);
            Response::success(['group_workout' => $result]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
