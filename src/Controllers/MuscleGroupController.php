<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\MuscleGroupModel;

class MuscleGroupController
{
    public function __construct(private readonly MuscleGroupModel $muscleGroups)
    {
    }

    public function index(Request $request): void
    {
        Response::success(['muscle_groups' => $this->muscleGroups->all()]);
    }
}
