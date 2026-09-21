<?php

namespace App\Controllers;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Services\StoryService;
use InvalidArgumentException;

class StoryController
{
    public function __construct(private readonly StoryService $stories)
    {
    }

    public function index(Request $request): void
    {
        Response::success(['groups' => $this->stories->listActive((int) $request->userId)]);
    }

    public function store(Request $request): void
    {
        try {
            $story = $this->stories->createStory(
                (int) $request->userId,
                (int) $request->input('media_id', 0),
                $request->input('caption') !== null ? (string) $request->input('caption') : null,
                (string) $request->input('visibility', 'friends')
            );
            Response::success(['story' => $story], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function show(Request $request): void
    {
        try {
            $story = $this->stories->getStory((int) $request->userId, (int) $request->params['id']);
            Response::success(['story' => $story]);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function view(Request $request): void
    {
        try {
            $this->stories->viewStory((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Vista registrada']);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function viewers(Request $request): void
    {
        try {
            $viewers = $this->stories->listViewers((int) $request->userId, (int) $request->params['id']);
            Response::success(['viewers' => $viewers]);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function destroy(Request $request): void
    {
        try {
            $this->stories->deleteStory((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Historia eliminada']);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        }
    }
}
