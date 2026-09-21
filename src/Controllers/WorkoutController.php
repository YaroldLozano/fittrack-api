<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\WorkoutService;
use InvalidArgumentException;

class WorkoutController
{
    public function __construct(private readonly WorkoutService $workouts)
    {
    }

    public function index(Request $request): void
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $sessions = $this->workouts->list((int) $request->userId, $from, $to);
        Response::success(['workouts' => $sessions]);
    }

    public function show(Request $request): void
    {
        try {
            $session = $this->workouts->get((int) $request->userId, (int) $request->params['id']);
            Response::success(['workout' => $session]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function store(Request $request): void
    {
        try {
            $session = $this->workouts->create((int) $request->userId, $request->body);
            Response::success(['workout' => $session], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function start(Request $request): void
    {
        try {
            $session = $this->workouts->start((int) $request->userId, (int) $request->params['id']);
            Response::success(['workout' => $session]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function complete(Request $request): void
    {
        try {
            $session = $this->workouts->complete((int) $request->userId, (int) $request->params['id']);
            Response::success(['workout' => $session]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function destroy(Request $request): void
    {
        try {
            $this->workouts->delete((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Entrenamiento eliminado']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function addSet(Request $request): void
    {
        try {
            $session = $this->workouts->addSet(
                (int) $request->userId,
                (int) $request->params['id'],
                $request->body
            );
            Response::success(['workout' => $session], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
