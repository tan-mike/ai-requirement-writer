<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\FeatureIntakeController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\ProjectController;
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
    
    // Projects
    Route::apiResource('projects', ProjectController::class);

    // Features
    Route::get('/projects/{project}/features', [FeatureController::class, 'index']);
    Route::post('/projects/{project}/features', [FeatureController::class, 'store']);
    Route::get('/features/{feature}', [FeatureController::class, 'show']);
    Route::patch('/features/{feature}', [FeatureController::class, 'update']);
    Route::delete('/features/{feature}', [FeatureController::class, 'destroy']);
    Route::post('/features/{feature}/complete', [FeatureController::class, 'complete']);
    Route::post('/features/{feature}/reset-discovery', [FeatureController::class, 'resetDiscovery']);

    // Intake
    Route::post('/features/{feature}/intake', [FeatureIntakeController::class, 'store']);

    // Generation (SSE streaming) - scoped to Feature
    Route::post('/features/{feature}/generate/brd', [GenerationController::class, 'brd']);
    Route::post('/features/{feature}/generate/stories', [GenerationController::class, 'stories']);
    Route::post('/features/{feature}/generate/spec', [GenerationController::class, 'spec']);
    Route::post('/features/{feature}/drafts/{draft}/synthesize', [GenerationController::class, 'synthesize']);

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

    // Drafts - scoped to Feature
    Route::get('/features/{feature}/drafts', [RequirementDraftController::class, 'index']);
    Route::get('/features/{feature}/drafts/{draft}', [RequirementDraftController::class, 'show']);
    Route::patch('/features/{feature}/drafts/{draft}', [RequirementDraftController::class, 'update']);
    Route::post('/features/{feature}/drafts/{draft}/approve', [RequirementDraftController::class, 'approve']);

    // Chat - scoped to Feature
    Route::get('/features/{feature}/messages', [\App\Http\Controllers\ChatController::class, 'index']);
    Route::delete('/features/{feature}/messages', [\App\Http\Controllers\ChatController::class, 'clear']);
    Route::post('/features/{feature}/chat', [\App\Http\Controllers\ChatController::class, 'chat']);
});

Route::middleware(['auth:sanctum', 'can:admin-access'])->prefix('admin')->group(function () {
    Route::get('/projects', [\App\Http\Controllers\Admin\ProjectCurationController::class, 'index']);
    Route::patch('/projects/{project}/curate', [\App\Http\Controllers\Admin\ProjectCurationController::class, 'update']);
    Route::post('/projects', [\App\Http\Controllers\Admin\ProjectCurationController::class, 'store']);
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'index']);
    Route::patch('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'update']);
});
