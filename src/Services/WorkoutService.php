<?php

namespace App\Services;

use App\Models\RoutineDayModel;
use App\Models\RoutineExerciseModel;
use App\Models\WorkoutSessionExerciseModel;
use App\Models\WorkoutSessionModel;
use App\Models\WorkoutSetModel;
use InvalidArgumentException;

class WorkoutService
{
    public function __construct(
        private readonly WorkoutSessionModel $sessions,
        private readonly WorkoutSessionExerciseModel $sessionExercises,
        private readonly WorkoutSetModel $sets,
        private readonly RoutineExerciseModel $routineExercises,
        private readonly RoutineDayModel $routineDays,
        private readonly GoalService $goalService,
        private readonly GamificationService $gamification,
        private readonly AchievementService $achievements
    ) {
    }

    public function list(int $userId, ?string $from, ?string $to): array
    {
        return $this->sessions->findAllForUser($userId, $from, $to);
    }

    public function get(int $userId, int $id): array
    {
        $session = $this->sessions->findOwnedBy($userId, $id);
        if ($session === null) {
            throw new InvalidArgumentException('Entrenamiento no encontrado');
        }

        $exercises = $this->sessionExercises->findBySession($id);
        $session['exercises'] = array_map(function ($ex) {
            $ex['sets'] = $this->sets->findBySessionExercise((int) $ex['id']);
            return $ex;
        }, $exercises);

        return $session;
    }

    public function create(int $userId, array $data): array
    {
        if (empty($data['name']) || empty($data['scheduled_date'])) {
            throw new InvalidArgumentException('name y scheduled_date son requeridos');
        }

        $sessionId = $this->sessions->create($userId, $data);

        if (!empty($data['exercises']) && is_array($data['exercises'])) {
            // Explicit exercise list provided by the client (ad-hoc workout).
            foreach ($data['exercises'] as $index => $ex) {
                $this->sessionExercises->create($sessionId, (int) $ex['exercise_id'], $index, $ex);
            }
        } elseif (!empty($data['routine_day_id'])) {
            // Snapshot the routine day's planned exercises/sets/reps/weight into the session.
            // Ownership check prevents pulling another user's routine plan via a guessed day id.
            if ($this->routineDays->findOwnedByUser($userId, (int) $data['routine_day_id']) === null) {
                throw new InvalidArgumentException('Día de rutina no encontrado');
            }
            foreach ($this->routineExercises->findByDay((int) $data['routine_day_id']) as $index => $planned) {
                $this->sessionExercises->create($sessionId, (int) $planned['exercise_id'], $index, $planned);
            }
        }

        return $this->get($userId, $sessionId);
    }

    public function start(int $userId, int $id): array
    {
        if ($this->sessions->findOwnedBy($userId, $id) === null) {
            throw new InvalidArgumentException('Entrenamiento no encontrado');
        }

        $this->sessions->updateStatusOwned($userId, $id, [
            'status' => 'in_progress',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->get($userId, $id);
    }

    public function complete(int $userId, int $id): array
    {
        $session = $this->sessions->findOwnedBy($userId, $id);
        if ($session === null) {
            throw new InvalidArgumentException('Entrenamiento no encontrado');
        }

        $now = new \DateTimeImmutable();
        $durationSeconds = null;
        if (!empty($session['started_at'])) {
            $durationSeconds = $now->getTimestamp() - (new \DateTimeImmutable($session['started_at']))->getTimestamp();
        }

        $this->sessions->updateStatusOwned($userId, $id, [
            'status' => 'completed',
            'completed_at' => $now->format('Y-m-d H:i:s'),
            'duration_seconds' => $durationSeconds,
        ]);

        $completedCount = count(array_filter(
            $this->sessions->findAllForUser($userId),
            fn ($s) => $s['status'] === 'completed'
        ));
        $this->goalService->onWorkoutCompleted($userId, $completedCount);
        $gamification = $this->gamification->onWorkoutCompleted($userId, $id, $durationSeconds);

        $result = $this->get($userId, $id);
        $result['gamification'] = $gamification;
        $result['new_achievements'] = $this->achievements->evaluate($userId);
        return $result;
    }

    public function delete(int $userId, int $id): void
    {
        if ($this->sessions->findOwnedBy($userId, $id) === null) {
            throw new InvalidArgumentException('Entrenamiento no encontrado');
        }
        $this->sessions->deleteOwned($userId, $id);
    }

    public function addSet(int $userId, int $sessionId, array $data): array
    {
        $session = $this->sessions->findOwnedBy($userId, $sessionId);
        if ($session === null) {
            throw new InvalidArgumentException('Entrenamiento no encontrado');
        }

        if (empty($data['exercise_id']) || !isset($data['reps']) || !isset($data['weight'])) {
            throw new InvalidArgumentException('exercise_id, reps y weight son requeridos');
        }

        $exerciseId = (int) $data['exercise_id'];
        $weight = (float) $data['weight'];

        $sessionExercise = $this->sessionExercises->findOne($sessionId, $exerciseId);
        if ($sessionExercise === null) {
            $existingCount = count($this->sessionExercises->findBySession($sessionId));
            $sessionExerciseId = $this->sessionExercises->create($sessionId, $exerciseId, $existingCount, $data);
        } else {
            $sessionExerciseId = (int) $sessionExercise['id'];
        }

        $previousBest = $this->sets->maxWeightBefore($userId, $exerciseId);
        $isPr = $previousBest === null || $weight > $previousBest;

        $setNumber = $data['set_number'] ?? (count($this->sets->findBySessionExercise($sessionExerciseId)) + 1);

        $setId = $this->sets->create($sessionExerciseId, $userId, $exerciseId, [
            'set_number' => $setNumber,
            'reps' => (int) $data['reps'],
            'weight' => $weight,
            'rest_seconds' => $data['rest_seconds'] ?? null,
            'completed_at' => $data['completed_at'] ?? date('Y-m-d H:i:s'),
            'is_personal_record' => $isPr,
            'previous_best_weight' => $previousBest,
        ]);

        $this->goalService->onSetLogged($userId, $exerciseId, $weight);
        $exerciseProgress = $this->gamification->onExerciseSetLogged($userId, $exerciseId, $isPr);

        $result = $this->get($userId, $sessionId);
        $result['exercise_progress'] = $exerciseProgress;
        if ($isPr) {
            $result['gamification'] = $this->gamification->onPersonalRecord($userId, $setId);
            $result['new_achievements'] = $this->achievements->evaluate($userId);
        }

        return $result;
    }
}
