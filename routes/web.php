<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SecurityController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Fault\DashboardController;
use App\Http\Controllers\Fault\IssueController;
use App\Http\Controllers\Fault\PublicIssueController;
use App\Http\Controllers\Fault\ReleaseController;
use App\Http\Controllers\Integrations\GithubAppController;
use App\Http\Controllers\Integrations\SlackAppController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\Organization\AuditLogController;
use App\Http\Controllers\Organization\InviteController;
use App\Http\Controllers\Organization\MemberController;
use App\Http\Controllers\Organization\SettingsController;
use App\Http\Controllers\Organization\SwitchController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');

    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.challenge.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/security', [SecurityController::class, 'edit'])->name('security.edit');
    Route::post('/security/avatar', [SecurityController::class, 'updateAvatar'])->name('security.avatar.update');
    Route::delete('/security/avatar', [SecurityController::class, 'destroyAvatar'])->name('security.avatar.destroy');
    Route::post('/security/tokens', [SecurityController::class, 'storeToken'])->name('security.tokens.store');
    Route::delete('/security/tokens/{tokenId}', [SecurityController::class, 'destroyToken'])->name('security.tokens.destroy');

    Route::post('/security/two-factor', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/security/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::delete('/security/two-factor', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
});

Route::get('/invites/{token}', [InviteController::class, 'show'])->name('invites.accept');

Route::get('/share/{token}', [PublicIssueController::class, 'show'])->name('share.show');
Route::post('/share/{token}/unlock', [PublicIssueController::class, 'unlock'])
    ->middleware('throttle:10,1')
    ->name('share.unlock');

Route::get('/integrations/github/callback', [GithubAppController::class, 'callback'])->name('integrations.github.callback');
Route::get('/integrations/slack/callback', [SlackAppController::class, 'callback'])->name('integrations.slack.callback');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [SwitchController::class, 'index'])->name('dashboard');
    Route::get('/organizations', [SwitchController::class, 'list'])->name('organizations.index');
    Route::post('/organizations', [SwitchController::class, 'store'])->name('organizations.store');

    Route::prefix('/o/{organization:slug}')->middleware('org.member')->group(function () {
        Route::get('/overview', [DashboardController::class, 'overview'])->name('organizations.overview');
        Route::get('/projects', [DashboardController::class, 'index'])->name('organizations.projects.index');
        Route::post('/projects', [DashboardController::class, 'store'])->name('organizations.projects.store');
        Route::get('/projects/{project:slug}', [DashboardController::class, 'show'])->name('organizations.projects.show');
        Route::post('/projects/{project:slug}/rotate-key', [DashboardController::class, 'rotateKey'])->name('organizations.projects.rotateKey');
        Route::patch('/projects/{project:slug}/settings', [DashboardController::class, 'updateSettings'])->name('organizations.projects.settings.update');
        Route::patch('/projects/{project:slug}/forwarding', [DashboardController::class, 'updateForwarding'])->name('organizations.projects.forwarding.update');
        Route::patch('/projects/{project:slug}/censorship', [DashboardController::class, 'updateCensorship'])->name('organizations.projects.censorship.update');
        Route::post('/projects/{project:slug}/transfer', [DashboardController::class, 'transfer'])->name('organizations.projects.transfer');
        Route::delete('/projects/{project:slug}', [DashboardController::class, 'destroy'])->name('organizations.projects.destroy');
        Route::post('/projects/{project:slug}/releases', [ReleaseController::class, 'store'])->name('organizations.projects.releases.store');
        Route::delete('/projects/{project:slug}/releases/{release}', [ReleaseController::class, 'destroy'])->name('organizations.projects.releases.destroy');

        Route::get('/projects/{project:slug}/issues/{issue}', [IssueController::class, 'show'])->name('organizations.issues.show');
        Route::get('/projects/{project:slug}/issues/{issue}/events/{event}', [IssueController::class, 'show'])->name('organizations.issues.events.show');
        Route::patch('/projects/{project:slug}/issues/{issue}', [IssueController::class, 'update'])->name('organizations.issues.update');
        Route::patch('/projects/{project:slug}/issues/{issue}/assign', [IssueController::class, 'assign'])->name('organizations.issues.assign');
        Route::post('/projects/{project:slug}/issues/{issue}/analyze', [IssueController::class, 'analyze'])->name('organizations.issues.analyze');
        Route::post('/projects/{project:slug}/issues/{issue}/deepen', [IssueController::class, 'deepen'])->name('organizations.issues.deepen');
        Route::post('/projects/{project:slug}/issues/{issue}/github', [IssueController::class, 'createGithubIssue'])->name('organizations.issues.github');

        Route::get('/monitoring', [MonitorController::class, 'index'])->name('organizations.monitors.index');
        Route::get('/monitoring/create', [MonitorController::class, 'create'])->name('organizations.monitors.create');
        Route::post('/monitoring', [MonitorController::class, 'store'])->name('organizations.monitors.store');
        Route::get('/monitoring/{monitor}', [MonitorController::class, 'show'])->name('organizations.monitors.show');
        Route::get('/monitoring/{monitor}/edit', [MonitorController::class, 'edit'])->name('organizations.monitors.edit');
        Route::patch('/monitoring/{monitor}', [MonitorController::class, 'update'])->name('organizations.monitors.update');
        Route::delete('/monitoring/{monitor}', [MonitorController::class, 'destroy'])->name('organizations.monitors.destroy');

        Route::get('/members', [MemberController::class, 'index'])->name('organizations.members.index');
        Route::patch('/members/{user}', [MemberController::class, 'updateRole'])->name('organizations.members.updateRole');
        Route::delete('/members/{user}', [MemberController::class, 'destroy'])->name('organizations.members.destroy');

        Route::post('/invites', [InviteController::class, 'store'])->name('organizations.invites.store');
        Route::delete('/invites/{invite}', [InviteController::class, 'destroy'])->name('organizations.invites.destroy');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('organizations.settings.edit');
        Route::patch('/settings', [SettingsController::class, 'update'])->name('organizations.settings.update');
        Route::delete('/settings', [SettingsController::class, 'destroy'])->name('organizations.settings.destroy');
        Route::patch('/settings/ai', [SettingsController::class, 'updateAi'])->name('organizations.settings.ai.update');
        Route::delete('/settings/ai', [SettingsController::class, 'destroyAi'])->name('organizations.settings.ai.destroy');
        Route::post('/leave', [SettingsController::class, 'leave'])->name('organizations.leave');

        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('organizations.audit.index');

        Route::get('/settings/integrations/github/redirect', [GithubAppController::class, 'redirect'])->name('integrations.github.redirect');
        Route::delete('/settings/integrations/github', [GithubAppController::class, 'disconnect'])->name('integrations.github.disconnect');
        Route::get('/settings/integrations/slack/redirect', [SlackAppController::class, 'redirect'])->name('integrations.slack.redirect');
        Route::delete('/settings/integrations/slack', [SlackAppController::class, 'disconnect'])->name('integrations.slack.disconnect');
    });
});
