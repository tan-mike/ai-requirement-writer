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

    // Drafts
    Route::get('/projects/{project}/drafts', [RequirementDraftController::class, 'index']);
    Route::patch('/projects/{project}/drafts/{draft}', [RequirementDraftController::class, 'update']);
    Route::post('/projects/{project}/drafts/{draft}/approve', [RequirementDraftController::class, 'approve']);

    // Chat
    Route::get('/projects/{project}/messages', [\App\Http\Controllers\ChatController::class, 'index']);
    Route::post('/projects/{project}/chat', [\App\Http\Controllers\ChatController::class, 'chat']);
});
