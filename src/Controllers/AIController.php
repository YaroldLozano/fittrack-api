<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AIService;
use InvalidArgumentException;
use RuntimeException;

class AIController
{
    public function __construct(private readonly AIService $ai)
    {
    }

    public function suggestWorkout(Request $request): void
    {
        try {
            $result = $this->ai->suggestWorkout(
                (int) $request->userId,
                (string) $request->input('prompt', ''),
                (array) $request->input('current_exercises', [])
            );
            Response::success(['suggestion' => $result]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 502);
        }
    }
}
