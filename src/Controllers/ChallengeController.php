<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ChallengeService;
use InvalidArgumentException;

class ChallengeController
{
    public function __construct(private readonly ChallengeService $challenges)
    {
    }

    public function index(Request $request): void
    {
        Response::success(['challenges' => $this->challenges->list((int) $request->userId)]);
    }

    public function store(Request $request): void
    {
        try {
            $challenge = $this->challenges->create((int) $request->userId, $request->body);
            Response::success(['challenge' => $challenge], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function show(Request $request): void
    {
        try {
            $challenge = $this->challenges->get((int) $request->userId, (int) $request->params['id']);
            Response::success(['challenge' => $challenge]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function accept(Request $request): void
    {
        $this->respond($request, true);
    }

    public function decline(Request $request): void
    {
        $this->respond($request, false);
    }

    private function respond(Request $request, bool $accept): void
    {
        try {
            $result = $this->challenges->respond((int) $request->userId, (int) $request->params['id'], $accept);
            Response::success(['challenge' => $result]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
