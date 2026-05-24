<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectIntakeController;
use App\Http\Controllers\RequirementDraftController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/templates', [TemplateController::class, 'index']);
    Route::apiResource('projects', ProjectController::class)->except(['update']);

    // Intake
    Route::post('/projects/{project}/intake', [ProjectIntakeController::class, 'store']);

    // Generation (SSE streaming)
    Route::post('/projects/{project}/generate/brd', [GenerationController::class, 'brd']);
    Route::post('/projects/{project}/generate/stories', [GenerationController::class, 'stories']);
    Route::post('/projects/{project}/generate/spec', [GenerationController::class, 'spec']);
    Route::post('/projects/{project}/drafts/{draft}/synthesize', [GenerationController::class, 'synthesize']);

    // Contexts
    Route::get('/contexts', [\App\Http\Controllers\ProjectContextController::class, 'index']);
    Route::post('/contexts', [\App\Http\Controllers\ProjectContextController::class, 'store']);
    Route::get('/contexts/{context}/history', [\App\Http\Controllers\ProjectContextController::class, 'history']);
    Route::patch('/contexts/{context}', [\App\Http\Controllers\ProjectContextController::class, 'update']);
    Route::delete('/contexts/{context}', [\App\Http\Controllers\ProjectContextController::class, 'destroy']);

    // Personas
    Route::get('/personas', function(\Illuminate\Http\Request $request) {
        $query = \App\Models\Persona::where('is_active', true)->visibleTo($request->user()->id);
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        return $query->get();
    });

    // Skill Management
    Route::get('/user/skills', [\App\Http\Controllers\SkillController::class, 'index']);
    Route::post('/user/skills/upload', [\App\Http\Controllers\SkillController::class, 'upload']);
    Route::delete('/user/skills/{persona}', [\App\Http\Controllers\SkillController::class, 'destroy']);

    // Teams
    Route::get('/teams', [\App\Http\Controllers\TeamController::class, 'index']);
    Route::post('/teams', [\App\Http\Controllers\TeamController::class, 'store']);
    Route::post('/teams/join', [\App\Http\Controllers\TeamController::class, 'join']);
    Route::patch('/user/current-team', [\App\Http\Controllers\TeamController::class, 'switch']);

    // User Settings
    Route::get('/user/me', \App\Http\Controllers\UserMeController::class);
    Route::get('/user/settings', [\App\Http\Controllers\UserSettingsController::class, 'show']);
    Route::patch('/user/settings', [\App\Http\Controllers\UserSettingsController::class, 'update']);

    // Drafts
    Route::get('/projects/{project}/drafts', [RequirementDraftController::class, 'index']);
    Route::get('/projects/{project}/drafts/{draft}', [RequirementDraftController::class, 'show']);
    Route::patch('/projects/{project}/drafts/{draft}', [RequirementDraftController::class, 'update']);
    Route::post('/projects/{project}/drafts/{draft}/approve', [RequirementDraftController::class, 'approve']);

    // Chat
    Route::get('/projects/{project}/messages', [\App\Http\Controllers\ChatController::class, 'index']);
    Route::post('/projects/{project}/chat', [\App\Http\Controllers\ChatController::class, 'chat']);
});
