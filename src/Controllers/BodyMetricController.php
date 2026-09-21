<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\BodyMetricService;
use InvalidArgumentException;

class BodyMetricController
{
    public function __construct(private readonly BodyMetricService $bodyMetrics)
    {
    }

    public function index(Request $request): void
    {
        Response::success(['body_metrics' => $this->bodyMetrics->list((int) $request->userId)]);
    }

    public function store(Request $request): void
    {
        try {
            $metric = $this->bodyMetrics->upsert((int) $request->userId, $request->body);
            Response::success(['body_metric' => $metric], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
