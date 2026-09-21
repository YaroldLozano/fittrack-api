<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ProgressService;
use InvalidArgumentException;

class ProgressController
{
    public function __construct(private readonly ProgressService $progress)
    {
    }

    public function general(Request $request): void
    {
        Response::success(['progress' => $this->progress->general((int) $request->userId)]);
    }

    public function forExercise(Request $request): void
    {
        try {
            $data = $this->progress->forExercise((int) $request->userId, (int) $request->params['id']);
            Response::success(['progress' => $data]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }
}
