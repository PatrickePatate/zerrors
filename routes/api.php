<?php

use App\Http\Controllers\Api\GithubWebhookController;
use App\Http\Controllers\Api\IssueApiController;
use App\Http\Controllers\Api\OrganizationApiController;
use App\Http\Controllers\Api\ProjectApiController;
use App\Http\Controllers\Fault\IngestController;
use Illuminate\Support\Facades\Route;

// No auth middleware here on purpose: SDKs authenticate via the public key
// embedded in the DSN, verified inside the controller against the project.
// The `api` route group has no CSRF verification, which is required since
// these are cross-origin POSTs from the Sentry SDK, not form submissions.

Route::middleware('throttle:fault-ingest')->group(function () {
    Route::post('/{projectId}/envelope/', [IngestController::class, 'envelope'])->whereNumber('projectId');
    Route::post('/{projectId}/store/', [IngestController::class, 'store'])->whereNumber('projectId');
});

// GitHub can't be given custom auth headers when configuring a webhook, so this
// single App-wide endpoint identifies the project by the pushed repository's
// full name and authenticates the request via GitHub's HMAC payload signature.
Route::post('/webhooks/github', [GithubWebhookController::class, 'handle'])
    ->middleware('throttle:fault-ingest');

// Read-only JSON API for personal access tokens (Laravel Sanctum).
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/organizations', [OrganizationApiController::class, 'index']);
    Route::get('/organizations/{organization:slug}/projects', [ProjectApiController::class, 'index']);
    Route::get('/organizations/{organization:slug}/projects/{project:slug}/issues', [IssueApiController::class, 'index']);
    Route::get('/organizations/{organization:slug}/projects/{project:slug}/issues/{issue}', [IssueApiController::class, 'show']);
});
