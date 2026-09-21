<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\RoutineService;
use InvalidArgumentException;

class RoutineController
{
    public function __construct(private readonly RoutineService $routines)
    {
    }

    public function index(Request $request): void
    {
        Response::success(['routines' => $this->routines->list((int) $request->userId)]);
    }

    public function show(Request $request): void
    {
        try {
            $routine = $this->routines->get((int) $request->userId, (int) $request->params['id']);
            Response::success(['routine' => $routine]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function store(Request $request): void
    {
        try {
            $routine = $this->routines->create((int) $request->userId, $request->body);
            Response::success(['routine' => $routine], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function update(Request $request): void
    {
        try {
            $routine = $this->routines->update((int) $request->userId, (int) $request->params['id'], $request->body);
            Response::success(['routine' => $routine]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function updateStatus(Request $request): void
    {
        try {
            $routine = $this->routines->updateStatus(
                (int) $request->userId,
                (int) $request->params['id'],
                (string) $request->input('status', '')
            );
            Response::success(['routine' => $routine]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function duplicate(Request $request): void
    {
        try {
            $routine = $this->routines->duplicate((int) $request->userId, (int) $request->params['id']);
            Response::success(['routine' => $routine], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function destroy(Request $request): void
    {
        try {
            $this->routines->delete((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Rutina eliminada']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }
}
