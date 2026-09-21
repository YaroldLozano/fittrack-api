<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\FriendService;
use InvalidArgumentException;

class FriendController
{
    public function __construct(private readonly FriendService $friends)
    {
    }

    public function search(Request $request): void
    {
        $results = $this->friends->search((int) $request->userId, (string) $request->input('q', ''));
        Response::success(['users' => $results]);
    }

    public function index(Request $request): void
    {
        Response::success(['friends' => $this->friends->listFriends((int) $request->userId)]);
    }

    public function incoming(Request $request): void
    {
        Response::success(['requests' => $this->friends->listIncomingRequests((int) $request->userId)]);
    }

    public function outgoing(Request $request): void
    {
        Response::success(['requests' => $this->friends->listOutgoingRequests((int) $request->userId)]);
    }

    public function sendRequest(Request $request): void
    {
        try {
            $result = $this->friends->sendRequest((int) $request->userId, (int) $request->input('user_id', 0));
            Response::success(['request' => $result], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function accept(Request $request): void
    {
        $this->respond($request, true);
    }

    public function reject(Request $request): void
    {
        $this->respond($request, false);
    }

    private function respond(Request $request, bool $accept): void
    {
        try {
            $result = $this->friends->respondToRequest((int) $request->userId, (int) $request->params['id'], $accept);
            Response::success(['request' => $result]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function cancel(Request $request): void
    {
        try {
            $this->friends->cancelRequest((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Solicitud cancelada']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function destroy(Request $request): void
    {
        try {
            $this->friends->removeFriend((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Amigo eliminado']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function block(Request $request): void
    {
        try {
            $this->friends->block((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Usuario bloqueado']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function profile(Request $request): void
    {
        try {
            $profile = $this->friends->publicProfile((int) $request->userId, (int) $request->params['id']);
            Response::success(['profile' => $profile]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function compare(Request $request): void
    {
        try {
            $comparison = $this->friends->compare((int) $request->userId, (int) $request->params['id']);
            Response::success(['comparison' => $comparison]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
