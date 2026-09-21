<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ExerciseService;
use InvalidArgumentException;

class ExerciseController
{
    public function __construct(private readonly ExerciseService $exercises)
    {
    }

    public function index(Request $request): void
    {
        $muscleGroupId = $request->input('muscle_group_id');
        $list = $this->exercises->list((int) $request->userId, $muscleGroupId !== null ? (int) $muscleGroupId : null);
        Response::success(['exercises' => $list]);
    }

    public function store(Request $request): void
    {
        try {
            $exercise = $this->exercises->create((int) $request->userId, $request->body);
            Response::success(['exercise' => $exercise], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function update(Request $request): void
    {
        try {
            $exercise = $this->exercises->update((int) $request->userId, (int) $request->params['id'], $request->body);
            Response::success(['exercise' => $exercise]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function destroy(Request $request): void
    {
        try {
            $this->exercises->delete((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Ejercicio eliminado']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }
}
