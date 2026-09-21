<?php

use App\Controllers\AchievementController;
use App\Controllers\AIController;
use App\Controllers\AuthController;
use App\Controllers\BodyMetricController;
use App\Controllers\ChallengeController;
use App\Controllers\ChatController;
use App\Controllers\ExerciseController;
use App\Controllers\MediaController;
use App\Controllers\NotificationController;
use App\Controllers\PostController;
use App\Controllers\ProfileController;
use App\Controllers\StoryController;
use App\Controllers\FriendController;
use App\Controllers\GoalController;
use App\Controllers\GroupWorkoutController;
use App\Controllers\MuscleGroupController;
use App\Controllers\ProgressController;
use App\Controllers\RankingController;
use App\Controllers\RoutineController;
use App\Controllers\WorkoutController;
use App\Core\Mailer;
use App\Core\Response;
use App\Models\BodyMetricModel;
use App\Models\AchievementModel;
use App\Models\BlockedUserModel;
use App\Models\ChallengeModel;
use App\Models\ChallengeParticipantModel;
use App\Models\ConversationModel;
use App\Models\ExerciseModel;
use App\Models\ExerciseProgressModel;
use App\Models\FriendRequestModel;
use App\Models\FriendshipModel;
use App\Models\GoalModel;
use App\Models\GroupWorkoutModel;
use App\Models\GroupWorkoutParticipantModel;
use App\Models\MediaModel;
use App\Models\MessageModel;
use App\Models\NotificationModel;
use App\Models\PostCommentModel;
use App\Models\PostLikeModel;
use App\Models\PostMediaModel;
use App\Models\PostModel;
use App\Models\StoryModel;
use App\Models\StoryViewModel;
use App\Models\MuscleGroupModel;
use App\Models\PasswordResetModel;
use App\Models\RankingModel;
use App\Models\RoutineDayModel;
use App\Models\RoutineExerciseModel;
use App\Models\RoutineModel;
use App\Models\SeasonModel;
use App\Models\TokenDenylistModel;
use App\Models\UserAchievementModel;
use App\Models\UserModel;
use App\Models\UserStatsModel;
use App\Models\WorkoutSessionExerciseModel;
use App\Models\WorkoutSessionModel;
use App\Models\WorkoutSetModel;
use App\Models\XpTransactionModel;
use App\Services\AchievementService;
use App\Services\AIService;
use App\Services\AuthService;
use App\Services\BodyMetricService;
use App\Services\ChallengeService;
use App\Services\ChatService;
use App\Services\ExerciseService;
use App\Services\FriendService;
use App\Services\GamificationService;
use App\Services\GoalService;
use App\Services\GroupWorkoutService;
use App\Services\MediaService;
use App\Services\NotificationService;
use App\Services\PasswordResetService;
use App\Services\PostService;
use App\Services\StoryService;
use App\Services\ProfileService;
use App\Services\ProgressService;
use App\Services\RankingService;
use App\Services\RoutineService;
use App\Services\WorkoutService;

/** @var \App\Core\Router $router */
/** @var array $config */
/** @var \App\Middleware\AuthMiddleware $authMiddleware */
/** @var \PDO $db */
/** @var \App\Core\Jwt $jwt */

$router->get('/health', function () {
    Response::success(['message' => 'ok', 'time' => date('c')]);
});

// --- Auth (público, salvo /auth/logout) ---
$authController = new AuthController(
    new AuthService(new UserModel($db), new TokenDenylistModel($db), $jwt),
    new PasswordResetService(new UserModel($db), new PasswordResetModel($db), new Mailer($config['mail']))
);

$router->post('/auth/register', [$authController, 'register']);
$router->post('/auth/login', [$authController, 'login']);
$router->post('/auth/forgot-password', [$authController, 'forgotPassword']);
$router->post('/auth/reset-password', [$authController, 'resetPassword']);
$router->post('/auth/logout', [$authController, 'logout'], [$authMiddleware]);
$router->put('/auth/email', [$authController, 'updateEmail'], [$authMiddleware]);

// --- Shared model instances ---
$muscleGroupModel = new MuscleGroupModel($db);
$exerciseModel = new ExerciseModel($db);
$goalModel = new GoalModel($db);

// --- Gamification (XP, niveles, rachas, temporadas) ---
$exerciseProgressModel = new ExerciseProgressModel($db);
$workoutSetModel = new WorkoutSetModel($db);
$gamificationService = new GamificationService(
    $db,
    new XpTransactionModel($db),
    new UserStatsModel($db),
    new SeasonModel($db),
    $exerciseProgressModel
);
$goalService = new GoalService($goalModel, $gamificationService);

// --- Shared social model instances (reutilizados por ranking, logros, amigos, retos y grupos) ---
$friendshipModel = new FriendshipModel($db);
$groupWorkoutParticipantModel = new GroupWorkoutParticipantModel($db);
$challengeParticipantModel = new ChallengeParticipantModel($db);
$userAchievementModel = new UserAchievementModel($db);

$rankingController = new RankingController(
    $gamificationService,
    new RankingService(
        new RankingModel($db),
        $friendshipModel,
        new SeasonModel($db),
        $exerciseModel,
        $gamificationService
    )
);
$router->get('/ranking/me', [$rankingController, 'me'], [$authMiddleware]);
$router->get('/ranking/global', [$rankingController, 'global'], [$authMiddleware]);
$router->get('/ranking/friends', [$rankingController, 'friends'], [$authMiddleware]);
$router->get('/ranking/weekly', [$rankingController, 'weekly'], [$authMiddleware]);
$router->get('/ranking/season', [$rankingController, 'season'], [$authMiddleware]);
$router->get('/ranking/exercise/:id', [$rankingController, 'exercise'], [$authMiddleware]);

// --- Achievements (logros) ---
$achievementModel = new AchievementModel($db);
$achievementService = new AchievementService(
    $achievementModel,
    $userAchievementModel,
    $workoutSetModel,
    $exerciseProgressModel,
    new UserStatsModel($db),
    $gamificationService,
    $friendshipModel,
    $groupWorkoutParticipantModel,
    $challengeParticipantModel
);
$achievementController = new AchievementController($achievementService);
$router->get('/achievements', [$achievementController, 'index'], [$authMiddleware]);
$router->get('/achievements/me', [$achievementController, 'me'], [$authMiddleware]);

// --- Notificaciones (usado por Amigos y, más abajo, Publicaciones) ---
$notificationService = new NotificationService(new NotificationModel($db));

// --- Friends ---
$userModel = new UserModel($db);
$friendService = new FriendService(
    $userModel,
    new UserStatsModel($db),
    new FriendRequestModel($db),
    $friendshipModel,
    new BlockedUserModel($db),
    new WorkoutSessionModel($db),
    $workoutSetModel,
    $exerciseProgressModel,
    $userAchievementModel,
    $notificationService
);
$friendController = new FriendController($friendService);
$router->get('/users/search', [$friendController, 'search'], [$authMiddleware]);
$router->get('/users/:id/profile', [$friendController, 'profile'], [$authMiddleware]);
$router->get('/users/:id/compare', [$friendController, 'compare'], [$authMiddleware]);
$router->post('/users/:id/block', [$friendController, 'block'], [$authMiddleware]);
$router->get('/friends', [$friendController, 'index'], [$authMiddleware]);
$router->get('/friends/incoming', [$friendController, 'incoming'], [$authMiddleware]);
$router->get('/friends/outgoing', [$friendController, 'outgoing'], [$authMiddleware]);
$router->post('/friends/request', [$friendController, 'sendRequest'], [$authMiddleware]);
$router->post('/friends/:id/accept', [$friendController, 'accept'], [$authMiddleware]);
$router->post('/friends/:id/reject', [$friendController, 'reject'], [$authMiddleware]);
$router->post('/friends/:id/cancel', [$friendController, 'cancel'], [$authMiddleware]);
$router->delete('/friends/:id', [$friendController, 'destroy'], [$authMiddleware]);

// --- Chat privado 1:1 entre amigos confirmados ---
$chatController = new ChatController(new ChatService(
    new ConversationModel($db),
    new MessageModel($db),
    $userModel,
    $friendshipModel,
    new BlockedUserModel($db)
));
$router->get('/messages/conversations', [$chatController, 'conversations'], [$authMiddleware]);
$router->post('/messages/conversations', [$chatController, 'createConversation'], [$authMiddleware]);
$router->get('/messages/conversations/:id', [$chatController, 'messages'], [$authMiddleware]);
$router->post('/messages/conversations/:id/messages', [$chatController, 'sendMessage'], [$authMiddleware]);
$router->post('/messages/conversations/:id/read', [$chatController, 'markRead'], [$authMiddleware]);
$router->delete('/messages/:id', [$chatController, 'deleteMessage'], [$authMiddleware]);

// --- Medios (fotos/videos subidos por posts, stories y avatar) ---
// Los archivos viven fuera de htdocs; este es el único punto por el que se sirven
// (streaming autenticado con chequeo de visibilidad/amistad/bloqueo) — ver plan.
$mediaModel = new MediaModel($db);
$mediaService = new MediaService(
    $db,
    $mediaModel,
    new \App\Core\MediaStorage(
        $config['storage']['media_path'],
        $config['storage']['max_image_bytes'],
        $config['storage']['max_video_bytes']
    ),
    $friendshipModel,
    new BlockedUserModel($db)
);
$mediaController = new MediaController($mediaService);
$router->post('/media', [$mediaController, 'upload'], [$authMiddleware]);
$router->get('/media/:id', [$mediaController, 'show'], [$authMiddleware]);
$router->delete('/media/:id', [$mediaController, 'destroy'], [$authMiddleware]);

// --- Perfil (editar bio/nombre, foto de perfil) ---
$profileController = new ProfileController(new ProfileService($userModel, $mediaModel, $mediaService));
$router->patch('/users/profile', [$profileController, 'update'], [$authMiddleware]);
$router->post('/users/profile/avatar', [$profileController, 'setAvatar'], [$authMiddleware]);
$router->delete('/users/profile/avatar', [$profileController, 'removeAvatar'], [$authMiddleware]);

// --- Notificaciones ---
$notificationController = new NotificationController($notificationService);
$router->get('/notifications', [$notificationController, 'index'], [$authMiddleware]);
$router->post('/notifications/:id/read', [$notificationController, 'markRead'], [$authMiddleware]);
$router->post('/notifications/read-all', [$notificationController, 'markAllRead'], [$authMiddleware]);

// --- Publicaciones (feed, likes, comentarios) ---
$postController = new PostController(new PostService(
    new PostModel($db),
    new PostMediaModel($db),
    new PostLikeModel($db),
    new PostCommentModel($db),
    $mediaModel,
    $userModel,
    new UserStatsModel($db),
    $friendshipModel,
    new BlockedUserModel($db),
    $notificationService
));
$router->get('/social/feed', [$postController, 'feed'], [$authMiddleware]);
$router->post('/posts', [$postController, 'store'], [$authMiddleware]);
$router->get('/posts/:id', [$postController, 'show'], [$authMiddleware]);
$router->patch('/posts/:id', [$postController, 'update'], [$authMiddleware]);
$router->delete('/posts/:id', [$postController, 'destroy'], [$authMiddleware]);
$router->get('/users/:id/posts', [$postController, 'forUser'], [$authMiddleware]);
$router->post('/posts/:id/like', [$postController, 'like'], [$authMiddleware]);
$router->delete('/posts/:id/like', [$postController, 'unlike'], [$authMiddleware]);
$router->get('/posts/:id/comments', [$postController, 'comments'], [$authMiddleware]);
$router->post('/posts/:id/comments', [$postController, 'storeComment'], [$authMiddleware]);
$router->delete('/comments/:id', [$postController, 'destroyComment'], [$authMiddleware]);

// --- Stories (contenido efímero, 24h) ---
$storyController = new StoryController(new StoryService(
    new StoryModel($db),
    new StoryViewModel($db),
    $mediaModel,
    $userModel,
    new UserStatsModel($db),
    $friendshipModel,
    new BlockedUserModel($db)
));
$router->get('/stories', [$storyController, 'index'], [$authMiddleware]);
$router->post('/stories', [$storyController, 'store'], [$authMiddleware]);
$router->get('/stories/:id', [$storyController, 'show'], [$authMiddleware]);
$router->post('/stories/:id/view', [$storyController, 'view'], [$authMiddleware]);
$router->get('/stories/:id/viewers', [$storyController, 'viewers'], [$authMiddleware]);
$router->delete('/stories/:id', [$storyController, 'destroy'], [$authMiddleware]);

// --- Challenges (retos entre amigos) ---
$challengeController = new ChallengeController(new ChallengeService(
    new ChallengeModel($db),
    $challengeParticipantModel,
    $friendshipModel,
    $workoutSetModel,
    new WorkoutSessionModel($db),
    new UserStatsModel($db),
    $gamificationService,
    $achievementService
));
$router->get('/challenges', [$challengeController, 'index'], [$authMiddleware]);
$router->post('/challenges', [$challengeController, 'store'], [$authMiddleware]);
$router->get('/challenges/:id', [$challengeController, 'show'], [$authMiddleware]);
$router->post('/challenges/:id/accept', [$challengeController, 'accept'], [$authMiddleware]);
$router->post('/challenges/:id/decline', [$challengeController, 'decline'], [$authMiddleware]);

// --- Group workouts (entrenamientos con amigos) ---
$groupWorkoutController = new GroupWorkoutController(new GroupWorkoutService(
    new GroupWorkoutModel($db),
    $groupWorkoutParticipantModel,
    $friendshipModel,
    $gamificationService,
    $achievementService
));
$router->get('/workouts/group', [$groupWorkoutController, 'index'], [$authMiddleware]);
$router->post('/workouts/group', [$groupWorkoutController, 'store'], [$authMiddleware]);
$router->post('/workouts/group/:id/invite', [$groupWorkoutController, 'invite'], [$authMiddleware]);
$router->post('/workouts/group/:id/respond', [$groupWorkoutController, 'respond'], [$authMiddleware]);
$router->post('/workouts/group/:id/complete', [$groupWorkoutController, 'complete'], [$authMiddleware]);

// --- Muscle groups (catálogo de referencia) ---
$muscleGroupController = new MuscleGroupController($muscleGroupModel);
$router->get('/muscle-groups', [$muscleGroupController, 'index'], [$authMiddleware]);

// --- Exercises ---
$exerciseController = new ExerciseController(new ExerciseService($exerciseModel, $muscleGroupModel));
$router->get('/exercises', [$exerciseController, 'index'], [$authMiddleware]);
$router->post('/exercises', [$exerciseController, 'store'], [$authMiddleware]);
$router->put('/exercises/:id', [$exerciseController, 'update'], [$authMiddleware]);
$router->delete('/exercises/:id', [$exerciseController, 'destroy'], [$authMiddleware]);

// --- IA: ajustar el entrenamiento del día a partir de una petición en lenguaje natural ---
$aiController = new AIController(new AIService($exerciseModel, $config['ai']['anthropicApiKey'], $config['ai']['anthropicModel']));
$router->post('/ai/suggest-workout', [$aiController, 'suggestWorkout'], [$authMiddleware]);

// --- Routines ---
$routineController = new RoutineController(new RoutineService(
    $db,
    new RoutineModel($db),
    new RoutineDayModel($db),
    new RoutineExerciseModel($db)
));
$router->get('/routines', [$routineController, 'index'], [$authMiddleware]);
$router->post('/routines', [$routineController, 'store'], [$authMiddleware]);
$router->get('/routines/:id', [$routineController, 'show'], [$authMiddleware]);
$router->put('/routines/:id', [$routineController, 'update'], [$authMiddleware]);
$router->delete('/routines/:id', [$routineController, 'destroy'], [$authMiddleware]);
$router->put('/routines/:id/status', [$routineController, 'updateStatus'], [$authMiddleware]);
$router->post('/routines/:id/duplicate', [$routineController, 'duplicate'], [$authMiddleware]);

// --- Workouts ---
$workoutSessionModel = new WorkoutSessionModel($db);
$workoutController = new WorkoutController(new WorkoutService(
    $workoutSessionModel,
    new WorkoutSessionExerciseModel($db),
    $workoutSetModel,
    new RoutineExerciseModel($db),
    new RoutineDayModel($db),
    $goalService,
    $gamificationService,
    $achievementService
));
$router->get('/workouts', [$workoutController, 'index'], [$authMiddleware]);
$router->post('/workouts', [$workoutController, 'store'], [$authMiddleware]);
$router->get('/workouts/:id', [$workoutController, 'show'], [$authMiddleware]);
$router->delete('/workouts/:id', [$workoutController, 'destroy'], [$authMiddleware]);
$router->post('/workouts/:id/start', [$workoutController, 'start'], [$authMiddleware]);
$router->post('/workouts/:id/complete', [$workoutController, 'complete'], [$authMiddleware]);
$router->post('/workouts/:id/sets', [$workoutController, 'addSet'], [$authMiddleware]);

// --- Progress ---
$bodyMetricModel = new BodyMetricModel($db);
$progressController = new ProgressController(new ProgressService(
    $workoutSetModel,
    $workoutSessionModel,
    $exerciseModel,
    $exerciseProgressModel,
    $bodyMetricModel
));
$router->get('/progress', [$progressController, 'general'], [$authMiddleware]);
$router->get('/progress/exercise/:id', [$progressController, 'forExercise'], [$authMiddleware]);

// --- Body metrics ---
$bodyMetricController = new BodyMetricController(new BodyMetricService($bodyMetricModel, $goalService));
$router->get('/body-metrics', [$bodyMetricController, 'index'], [$authMiddleware]);
$router->post('/body-metrics', [$bodyMetricController, 'store'], [$authMiddleware]);

// --- Goals ---
$goalController = new GoalController($goalService);
$router->get('/goals', [$goalController, 'index'], [$authMiddleware]);
$router->post('/goals', [$goalController, 'store'], [$authMiddleware]);
$router->put('/goals/:id', [$goalController, 'update'], [$authMiddleware]);
$router->delete('/goals/:id', [$goalController, 'destroy'], [$authMiddleware]);
