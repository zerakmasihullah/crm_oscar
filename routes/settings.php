<?php

use App\Http\Controllers\Settings\ActivityLogController;
use App\Http\Controllers\Settings\AiSettingsController;
use App\Http\Controllers\Settings\AppearanceSettingsController;
use App\Http\Controllers\Settings\AuthenticationLogController;
use App\Http\Controllers\Settings\BackupController;
use App\Http\Controllers\Settings\ChunkedUploadController;
use App\Http\Controllers\Settings\EmailSettingsController;
use App\Http\Controllers\Settings\FileManagerController;
use App\Http\Controllers\Settings\GeneralSettingsController;
use App\Http\Controllers\Settings\GoogleAuthSettingsController;
use App\Http\Controllers\Settings\OfflineSettingsController;
use App\Http\Controllers\Settings\PlatformController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RoleManagementController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SystemControlSettingsController;
use App\Http\Controllers\Settings\SystemLogController;
use App\Http\Controllers\Settings\TranslationController;
use App\Http\Controllers\Settings\TrashController;
use App\Http\Controllers\Settings\UserManagementController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin.access'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified', 'admin.access'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::post('settings/security/sessions/revoke-others', [SecurityController::class, 'revokeOtherSessions'])
        ->middleware(['throttle:6,1', RequirePassword::class])
        ->name('security.sessions.revoke-others');

    Route::get('settings/appearance', [AppearanceSettingsController::class, 'edit'])->name('appearance.edit');
    Route::put('settings/appearance/colors', [AppearanceSettingsController::class, 'updateColors'])->name('appearance.colors.update');
    Route::delete('settings/appearance/colors', [AppearanceSettingsController::class, 'resetColors'])->name('appearance.colors.reset');

    Route::get('settings/general', [GeneralSettingsController::class, 'edit'])->name('settings.general.edit');
    Route::put('settings/general', [GeneralSettingsController::class, 'update'])->name('settings.general.update');

    Route::get('settings/offline', [OfflineSettingsController::class, 'edit'])->name('settings.offline.edit');
    Route::put('settings/offline', [OfflineSettingsController::class, 'update'])->name('settings.offline.update');
    Route::delete('settings/offline/devices/{device}', [OfflineSettingsController::class, 'revokeDevice'])->name('settings.offline.devices.revoke');

    Route::get('settings/google-auth', [GoogleAuthSettingsController::class, 'edit'])->name('settings.google-auth.edit');
    Route::put('settings/google-auth', [GoogleAuthSettingsController::class, 'update'])->name('settings.google-auth.update');

    Route::get('settings/system-control', [SystemControlSettingsController::class, 'edit'])->name('settings.system-control.edit');
    Route::put('settings/system-control', [SystemControlSettingsController::class, 'update'])->name('settings.system-control.update');

    Route::get('settings/ai', [AiSettingsController::class, 'edit'])->name('settings.ai.edit');
    Route::get('settings/ai/prompts', [AiSettingsController::class, 'prompts'])->name('settings.ai.prompts');
    Route::get('settings/ai/assist', [AiSettingsController::class, 'assist'])->name('settings.ai.assist');
    Route::get('settings/ai/auto', [AiSettingsController::class, 'auto'])->name('settings.ai.auto');
    Route::get('settings/ai/tasks', [AiSettingsController::class, 'tasks'])->name('settings.ai.tasks');
    Route::get('settings/ai/data', [AiSettingsController::class, 'data'])->name('settings.ai.data');
    Route::put('settings/ai', [AiSettingsController::class, 'update'])->name('settings.ai.update');
    Route::put('settings/ai/resource-access', [AiSettingsController::class, 'updateResourceAccess'])
        ->name('settings.ai.resource-access');
    Route::post('settings/ai/langfuse/test', [AiSettingsController::class, 'testLangfuse'])
        ->middleware('throttle:10,1')
        ->name('settings.ai.langfuse.test');
    Route::get('settings/ai/logs', [AiSettingsController::class, 'logs'])->name('settings.ai.logs');

    Route::get('settings/users', [UserManagementController::class, 'index'])->name('settings.users.index');
    Route::get('settings/users/export', [UserManagementController::class, 'export'])->name('settings.users.export');
    Route::get('settings/users/print', [UserManagementController::class, 'print'])->name('settings.users.print');
    Route::get('settings/users/create', [UserManagementController::class, 'create'])->name('settings.users.create');
    Route::post('settings/users', [UserManagementController::class, 'store'])->name('settings.users.store');
    Route::patch('settings/users/bulk-status', [UserManagementController::class, 'bulkStatus'])->name('settings.users.bulk-status');
    Route::delete('settings/users/bulk', [UserManagementController::class, 'bulkDestroy'])->name('settings.users.bulk-destroy');
    Route::patch('settings/users/{user}', [UserManagementController::class, 'update'])->name('settings.users.update');
    Route::delete('settings/users/{user}', [UserManagementController::class, 'destroy'])->name('settings.users.destroy');

    Route::get('settings/roles', [RoleManagementController::class, 'index'])->name('settings.roles.index');
    Route::get('settings/roles/create', [RoleManagementController::class, 'create'])->name('settings.roles.create');
    Route::post('settings/roles', [RoleManagementController::class, 'store'])->name('settings.roles.store');
    Route::delete('settings/roles/bulk', [RoleManagementController::class, 'bulkDestroy'])->name('settings.roles.bulk-destroy');
    Route::patch('settings/roles/{role}', [RoleManagementController::class, 'update'])->name('settings.roles.update');
    Route::delete('settings/roles/{role}', [RoleManagementController::class, 'destroy'])->name('settings.roles.destroy');

    Route::resource('settings/platforms', PlatformController::class)
        ->parameters(['platforms' => 'platform'])
        ->names('settings.platforms')
        ->except(['show']);

    Route::get('settings/backups', [BackupController::class, 'index'])->name('settings.backups.index');
    Route::post('settings/backups', [BackupController::class, 'store'])->name('settings.backups.store');
    Route::put('settings/backups/auto', [BackupController::class, 'updateAuto'])->name('settings.backups.auto');
    Route::delete('settings/backups/bulk', [BackupController::class, 'bulkDestroy'])->name('settings.backups.bulk-destroy');
    Route::post('settings/backups/{backup}/restore', [BackupController::class, 'restore'])->name('settings.backups.restore');
    Route::get('settings/backups/{backup}/download', [BackupController::class, 'download'])->name('settings.backups.download');
    Route::delete('settings/backups/{backup}', [BackupController::class, 'destroy'])->name('settings.backups.destroy');

    Route::get('settings/file-manager', [FileManagerController::class, 'index'])->name('settings.file-manager.index');
    Route::post('settings/file-manager/folders', [FileManagerController::class, 'storeFolder'])->name('settings.file-manager.folders.store');
    Route::post('settings/file-manager/upload', [FileManagerController::class, 'upload'])->name('settings.file-manager.upload');
    Route::put('settings/file-manager/rename', [FileManagerController::class, 'rename'])->name('settings.file-manager.rename');
    Route::put('settings/file-manager/move', [FileManagerController::class, 'move'])->name('settings.file-manager.move');
    Route::delete('settings/file-manager', [FileManagerController::class, 'destroy'])->name('settings.file-manager.destroy');
    Route::get('settings/file-manager/download', [FileManagerController::class, 'download'])->name('settings.file-manager.download');
    Route::get('settings/file-manager/preview', [FileManagerController::class, 'preview'])->name('settings.file-manager.preview');

    Route::get('settings/uploads/config', [ChunkedUploadController::class, 'config'])
        ->name('settings.uploads.config');
    Route::post('settings/uploads', [ChunkedUploadController::class, 'initiate'])
        ->middleware('throttle:30,1')
        ->name('settings.uploads.initiate');
    Route::get('settings/uploads/{uploadSession}', [ChunkedUploadController::class, 'status'])
        ->name('settings.uploads.status');
    Route::post('settings/uploads/{uploadSession}/chunks', [ChunkedUploadController::class, 'chunk'])
        ->middleware('throttle:600,1')
        ->name('settings.uploads.chunk');
    Route::post('settings/uploads/{uploadSession}/complete', [ChunkedUploadController::class, 'complete'])
        ->middleware('throttle:60,1')
        ->name('settings.uploads.complete');
    Route::delete('settings/uploads/{uploadSession}', [ChunkedUploadController::class, 'abort'])
        ->middleware('throttle:60,1')
        ->name('settings.uploads.abort');

    Route::get('settings/email', [EmailSettingsController::class, 'edit'])->name('settings.email.edit');
    Route::put('settings/email', [EmailSettingsController::class, 'update'])->name('settings.email.update');
    Route::get('settings/email/templates', [EmailSettingsController::class, 'templates'])->name('settings.email.templates');
    Route::put('settings/email/templates', [EmailSettingsController::class, 'updateTemplate'])->name('settings.email.templates.update');
    Route::post('settings/email/test', [EmailSettingsController::class, 'sendTest'])->name('settings.email.test');
    Route::get('settings/email/logs', [EmailSettingsController::class, 'logs'])->name('settings.email.logs');

    Route::get('settings/login-logs', [AuthenticationLogController::class, 'index'])->name('settings.login-logs.index');
    Route::get('settings/action-logs', [ActivityLogController::class, 'index'])->name('settings.action-logs.index');
    Route::get('settings/system-logs', [SystemLogController::class, 'index'])->name('settings.system-logs.index');
    Route::get('settings/system-logs/download', [SystemLogController::class, 'download'])->name('settings.system-logs.download');
    Route::delete('settings/system-logs', [SystemLogController::class, 'destroy'])->name('settings.system-logs.destroy');

    Route::get('settings/trash', [TrashController::class, 'index'])->name('settings.trash.index');
    Route::post('settings/trash/restore', [TrashController::class, 'restore'])->name('settings.trash.restore');
    Route::post('settings/trash/bulk-restore', [TrashController::class, 'bulkRestore'])->name('settings.trash.bulk-restore');
    Route::delete('settings/trash/item', [TrashController::class, 'forceDestroy'])->name('settings.trash.force-destroy');
    Route::delete('settings/trash/bulk', [TrashController::class, 'bulkForceDestroy'])->name('settings.trash.bulk-force-destroy');
    Route::delete('settings/trash', [TrashController::class, 'empty'])->name('settings.trash.empty');

    Route::get('settings/translations', [TranslationController::class, 'index'])->name('settings.translations.index');
    Route::post('settings/translations', [TranslationController::class, 'store'])->name('settings.translations.store');
    Route::put('settings/translations', [TranslationController::class, 'update'])->name('settings.translations.update');

});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('profile.edit', ['section' => 'passkeys']),
        'manage' => route('profile.edit', ['section' => 'passkeys']),
    ]);
})->name('well-known.passkeys');
