<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ExternalExerciseService;
use RuntimeException;

class ExternalExerciseController
{
    public function __construct(private readonly ExternalExerciseService $externalExercises)
    {
    }

    public function categories(Request $request): void
    {
        try {
            $categories = $this->externalExercises->categories();
            Response::success(['categories' => $categories]);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 502);
        }
    }

    public function index(Request $request): void
    {
        $categoryId = $request->input('category');
        $query = $request->input('search');
        $page = (int) ($request->input('page') ?? 1);

        try {
            $result = $this->externalExercises->search(
                $categoryId !== null ? (int) $categoryId : null,
                $query !== null ? (string) $query : null,
                $page > 0 ? $page : 1
            );
            Response::success($result);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 502);
        }
    }
}
