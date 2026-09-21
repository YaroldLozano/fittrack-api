<?php

namespace App\Core;

class Request
{
    public string $method;
    public string $path;
    /** @var array<string, mixed> */
    public array $body;
    /** @var array<string, mixed> */
    public array $query;
    /** @var array<string, string> */
    public array $params = [];
    public ?int $userId = null;
    public ?string $jti = null;
    public ?int $tokenExp = null;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }
        $this->path = '/' . trim($uri, '/');

        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        $this->body = is_array($decoded) ? $decoded : [];
        $this->query = $_GET ?? [];
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
