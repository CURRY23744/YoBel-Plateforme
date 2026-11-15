<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AiAssistantController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DecisionController;
use App\Http\Controllers\Api\DiscussionController;
use App\Http\Controllers\Api\EvaluationCriterionController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FolderController;
use App\Http\Controllers\Api\MeetingMinuteController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectEvaluationController;
use App\Http\Controllers\Api\ProjectFileController;
use App\Http\Controllers\Api\ProjectMemberController;
use App\Http\Controllers\Api\ProjectTypeController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de la plateforme de gestion de projets  (préfixe automatique : /api)
|--------------------------------------------------------------------------
*/

// ---------- Public ----------
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
});

// ---------- Authentifié ----------
Route::middleware(['auth:sanctum', 'active'])->group(function () {

    // Compte
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'currentUser']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::post('profile', [AuthController::class, 'updateProfile']); // pour l'envoi d'avatar (multipart)
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });

    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('search', [SearchController::class, 'index']);
    Route::middleware('role:admin,project_manager,member')->prefix('ai')->group(function () {
        Route::get('interactions', [AiAssistantController::class, 'index']);
        Route::post('ask', [AiAssistantController::class, 'ask'])->middleware('throttle:20,1');
        Route::post('suggestions/{suggestion}/review', [AiAssistantController::class, 'reviewSuggestion']);
    });

    // Utilisateurs
    Route::middleware('role:admin,project_manager')->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{user}', [UserController::class, 'show']);
    });
    Route::middleware('role:admin')->group(function () {
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive']);
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
    });

    // Types de projets
    Route::get('project-types', [ProjectTypeController::class, 'index']);
    Route::get('project-types/{projectType}', [ProjectTypeController::class, 'show']);
    Route::middleware('role:admin')->group(function () {
        Route::post('project-types', [ProjectTypeController::class, 'store']);
        Route::put('project-types/{projectType}', [ProjectTypeController::class, 'update']);
        Route::delete('project-types/{projectType}', [ProjectTypeController::class, 'destroy']);
    });

    // Clients et étiquettes
    Route::get('clients', [ClientController::class, 'index']);
    Route::get('clients/{client}', [ClientController::class, 'show']);
    Route::get('tags', [TagController::class, 'index']);
    Route::middleware('role:admin,project_manager')->group(function () {
        Route::post('clients', [ClientController::class, 'store']);
        Route::put('clients/{client}', [ClientController::class, 'update']);
        Route::delete('clients/{client}', [ClientController::class, 'destroy']);
        Route::post('tags', [TagController::class, 'store']);
        Route::delete('tags/{tag}', [TagController::class, 'destroy']);
    });

    // Projets
    Route::get('projects/stats', [ProjectController::class, 'stats']);
    Route::post('projects/{project}/archive', [ProjectController::class, 'archive']);
    Route::post('projects/{project}/restore', [ProjectController::class, 'restore']);
    Route::post('projects/{project}/duplicate', [ProjectController::class, 'duplicate']);
    Route::apiResource('projects', ProjectController::class);

    // Équipe du projet
    Route::get('projects/{project}/members', [ProjectMemberController::class, 'index']);
    Route::post('projects/{project}/members', [ProjectMemberController::class, 'store']);
    Route::put('projects/{project}/members/{user}', [ProjectMemberController::class, 'update']);
    Route::delete('projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy']);

    // Jalons
    Route::get('projects/{project}/milestones', [MilestoneController::class, 'index']);
    Route::post('projects/{project}/milestones', [MilestoneController::class, 'store']);
    Route::put('milestones/{milestone}', [MilestoneController::class, 'update']);
    Route::delete('milestones/{milestone}', [MilestoneController::class, 'destroy']);

    // Dossiers
    Route::get('projects/{project}/folders', [FolderController::class, 'index']);
    Route::post('projects/{project}/folders', [FolderController::class, 'store']);
    Route::get('folders/{folder}', [FolderController::class, 'show']);
    Route::put('folders/{folder}', [FolderController::class, 'update']);
    Route::delete('folders/{folder}', [FolderController::class, 'destroy']);
    Route::put('folders/{folder}/permissions', [FolderController::class, 'permissions']);

    // Fichiers
    Route::get('projects/{project}/files', [ProjectFileController::class, 'index']);
    Route::post('projects/{project}/files', [ProjectFileController::class, 'store']);
    Route::get('files/{file}', [ProjectFileController::class, 'show']);
    Route::put('files/{file}', [ProjectFileController::class, 'update']);
    Route::delete('files/{file}', [ProjectFileController::class, 'destroy']);
    Route::get('files/{file}/download', [ProjectFileController::class, 'download']);
    Route::get('files/{file}/preview', [ProjectFileController::class, 'preview']);
    Route::get('files/{file}/versions', [ProjectFileController::class, 'versions']);
    Route::post('files/{file}/versions', [ProjectFileController::class, 'newVersion']);

    // Discussions : par projet, et espace général
    Route::get('projects/{project}/discussions', [DiscussionController::class, 'index']);
    Route::post('projects/{project}/discussions', [DiscussionController::class, 'store']);
    Route::get('discussions', [DiscussionController::class, 'index']);
    Route::post('discussions', [DiscussionController::class, 'store']);
    Route::get('discussions/{discussion}', [DiscussionController::class, 'show']);
    Route::put('discussions/{discussion}', [DiscussionController::class, 'update']);
    Route::delete('discussions/{discussion}', [DiscussionController::class, 'destroy']);
    Route::get('discussions/{discussion}/messages', [MessageController::class, 'index']);
    Route::post('discussions/{discussion}/messages', [MessageController::class, 'store']);
    Route::put('messages/{message}', [MessageController::class, 'update']);
    Route::delete('messages/{message}', [MessageController::class, 'destroy']);

    // Tâches
    Route::get('tasks', [TaskController::class, 'index']);
    Route::post('tasks', [TaskController::class, 'store']);
    Route::post('projects/{project}/tasks', [TaskController::class, 'store']);
    Route::get('tasks/{task}', [TaskController::class, 'show']);
    Route::put('tasks/{task}', [TaskController::class, 'update']);
    Route::delete('tasks/{task}', [TaskController::class, 'destroy']);

    // Registre des décisions
    Route::get('decisions', [DecisionController::class, 'index']);
    Route::post('decisions', [DecisionController::class, 'store']);
    Route::get('projects/{project}/decisions', [DecisionController::class, 'index']);
    Route::post('projects/{project}/decisions', [DecisionController::class, 'store']);
    Route::get('decisions/{decision}', [DecisionController::class, 'show']);
    Route::put('decisions/{decision}', [DecisionController::class, 'update']);
    Route::delete('decisions/{decision}', [DecisionController::class, 'destroy']);

    // Évaluation et priorisation (direction)
    Route::get('evaluations/ranking', [ProjectEvaluationController::class, 'ranking']);
    Route::get('evaluation-criteria', [EvaluationCriterionController::class, 'index']);
    Route::middleware('role:admin')->group(function () {
        Route::post('evaluation-criteria', [EvaluationCriterionController::class, 'store']);
        Route::put('evaluation-criteria/{criterion}', [EvaluationCriterionController::class, 'update']);
        Route::delete('evaluation-criteria/{criterion}', [EvaluationCriterionController::class, 'destroy']);
    });
    Route::get('projects/{project}/evaluations', [ProjectEvaluationController::class, 'show']);
    Route::post('projects/{project}/evaluations', [ProjectEvaluationController::class, 'store']);

    // Agenda
    Route::get('events', [EventController::class, 'index']);
    Route::post('events', [EventController::class, 'store']);
    Route::get('events/{event}', [EventController::class, 'show']);
    Route::put('events/{event}', [EventController::class, 'update']);
    Route::delete('events/{event}', [EventController::class, 'destroy']);
    Route::post('events/{event}/respond', [EventController::class, 'respond']);

    // Annonces
    Route::get('announcements', [AnnouncementController::class, 'index']);
    Route::get('announcements/{announcement}', [AnnouncementController::class, 'show']);
    Route::middleware('role:admin,project_manager')->group(function () {
        Route::post('announcements', [AnnouncementController::class, 'store']);
        Route::put('announcements/{announcement}', [AnnouncementController::class, 'update']);
        Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy']);
    });

    // Comptes rendus de réunion
    Route::get('meeting-minutes', [MeetingMinuteController::class, 'index']);
    Route::post('meeting-minutes', [MeetingMinuteController::class, 'store']);
    Route::get('projects/{project}/meeting-minutes', [MeetingMinuteController::class, 'index']);
    Route::post('projects/{project}/meeting-minutes', [MeetingMinuteController::class, 'store']);
    Route::get('meeting-minutes/{minute}', [MeetingMinuteController::class, 'show']);
    Route::put('meeting-minutes/{minute}', [MeetingMinuteController::class, 'update']);
    Route::delete('meeting-minutes/{minute}', [MeetingMinuteController::class, 'destroy']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);

    // Journal d'activité
    Route::get('projects/{project}/activity', [ActivityLogController::class, 'project']);
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->middleware('role:admin');
});
