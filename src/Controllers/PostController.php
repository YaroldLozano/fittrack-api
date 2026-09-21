<?php

namespace App\Controllers;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PostService;
use InvalidArgumentException;

class PostController
{
    public function __construct(private readonly PostService $posts)
    {
    }

    public function feed(Request $request): void
    {
        $before = $request->input('before');
        $result = $this->posts->feed(
            (int) $request->userId,
            $before !== null ? (int) $before : null,
            (int) $request->input('limit', 20)
        );
        Response::success($result);
    }

    public function store(Request $request): void
    {
        try {
            $mediaIds = $request->input('media_ids', []);
            if (!is_array($mediaIds)) {
                $mediaIds = [];
            }
            $post = $this->posts->createPost(
                (int) $request->userId,
                $request->input('caption') !== null ? (string) $request->input('caption') : null,
                (string) $request->input('post_type', 'text'),
                $request->input('context'),
                (string) $request->input('visibility', 'friends'),
                $mediaIds
            );
            Response::success(['post' => $post], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function show(Request $request): void
    {
        try {
            $post = $this->posts->getPost((int) $request->userId, (int) $request->params['id']);
            Response::success(['post' => $post]);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function update(Request $request): void
    {
        try {
            $fields = [];
            if (array_key_exists('caption', $request->body)) {
                $fields['caption'] = $request->body['caption'];
            }
            if (array_key_exists('visibility', $request->body)) {
                $fields['visibility'] = $request->body['visibility'];
            }
            $post = $this->posts->updatePost((int) $request->userId, (int) $request->params['id'], $fields);
            Response::success(['post' => $post]);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function destroy(Request $request): void
    {
        try {
            $this->posts->deletePost((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Publicación eliminada']);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function forUser(Request $request): void
    {
        $before = $request->input('before');
        $result = $this->posts->profilePosts(
            (int) $request->userId,
            (int) $request->params['id'],
            $before !== null ? (int) $before : null,
            (int) $request->input('limit', 20)
        );
        Response::success($result);
    }

    public function like(Request $request): void
    {
        try {
            $this->posts->like((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Me gusta agregado']);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function unlike(Request $request): void
    {
        try {
            $this->posts->unlike((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Me gusta quitado']);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function comments(Request $request): void
    {
        try {
            $comments = $this->posts->listComments((int) $request->userId, (int) $request->params['id']);
            Response::success(['comments' => $comments]);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        }
    }

    public function storeComment(Request $request): void
    {
        try {
            $comment = $this->posts->addComment(
                (int) $request->userId,
                (int) $request->params['id'],
                (string) $request->input('comment', '')
            );
            Response::success(['comment' => $comment], 201);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function destroyComment(Request $request): void
    {
        try {
            $this->posts->deleteComment((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Comentario eliminado']);
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        }
    }
}
