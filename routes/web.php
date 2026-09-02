<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Portal;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Note what is NOT here: there is no registration route of any kind. Accounts
| are created solely by an administrator's invitation.
|
*/

Route::get('/', function () {
    $user = request()->user();

    if ($user === null) {
        return redirect()->route('login');
    }

    return redirect()->route($user->isAdmin() ? 'admin.dashboard' : 'portal.dashboard');
})->name('home');

Route::get('/impressum', [LegalController::class, 'imprint'])->name('legal.imprint');
Route::get('/datenschutz', [LegalController::class, 'privacy'])->name('legal.privacy');

Route::middleware('guest')->group(function () {
    Route::get('/login', [Auth\AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [Auth\AuthenticatedSessionController::class, 'store']);

    Route::get('/passwort-vergessen', [Auth\PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/passwort-vergessen', [Auth\PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('/passwort-zuruecksetzen/{token}', [Auth\NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/passwort-zuruecksetzen', [Auth\NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');

    // Redeeming an invitation. Throttled because the token is the only secret.
    Route::get('/einladung/{token}', [Auth\InvitationRedemptionController::class, 'create'])
        ->middleware('throttle:20,1')
        ->name('invitations.show');
    Route::post('/einladung/{token}', [Auth\InvitationRedemptionController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('invitations.accept');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes (both roles)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::post('/logout', [Auth\AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/passwort', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

/*
|--------------------------------------------------------------------------
| Administrator area
|--------------------------------------------------------------------------
|
| Guarded twice: the "admin" middleware answers 404 for everybody else, and
| every controller action additionally authorises against a policy.
|
*/

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('kunden', [Admin\CustomerController::class, 'index'])->name('customers.index');
        Route::get('kunden/neu', [Admin\CustomerController::class, 'create'])->name('customers.create');
        Route::post('kunden', [Admin\CustomerController::class, 'store'])->name('customers.store');
        Route::get('kunden/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
        Route::get('kunden/{customer}/bearbeiten', [Admin\CustomerController::class, 'edit'])->name('customers.edit');
        Route::patch('kunden/{customer}', [Admin\CustomerController::class, 'update'])->name('customers.update');

        Route::get('kunden/{customer}/benutzer', [Admin\CustomerUserController::class, 'index'])
            ->name('customers.users.index');
        Route::post('kunden/{customer}/einladungen', [Admin\CustomerUserController::class, 'invite'])
            ->name('customers.invitations.store');
        Route::post('kunden/{customer}/einladungen/{invitation}/erneut', [Admin\CustomerUserController::class, 'resend'])
            ->name('customers.invitations.resend');
        Route::delete('kunden/{customer}/einladungen/{invitation}', [Admin\CustomerUserController::class, 'revoke'])
            ->name('customers.invitations.revoke');
        Route::patch('kunden/{customer}/benutzer/{user}/sperre', [Admin\CustomerUserController::class, 'toggleBlock'])
            ->name('customers.users.block');

        Route::get('projekte', [Admin\ProjectController::class, 'index'])->name('projects.index');
        Route::get('projekte/neu', [Admin\ProjectController::class, 'create'])->name('projects.create');
        Route::post('projekte', [Admin\ProjectController::class, 'store'])->name('projects.store');
        Route::get('projekte/{project}', [Admin\ProjectController::class, 'show'])->name('projects.show');
        Route::get('projekte/{project}/bearbeiten', [Admin\ProjectController::class, 'edit'])->name('projects.edit');
        Route::patch('projekte/{project}', [Admin\ProjectController::class, 'update'])->name('projects.update');

        // No separate preview index: previews are managed on the project page,
        // which is the only place they exist as far as the administrator is
        // concerned.
        Route::get('projekte/{project}/vorschauen/neu', [Admin\PreviewController::class, 'create'])
            ->name('projects.previews.create');
        Route::post('projekte/{project}/vorschauen', [Admin\PreviewController::class, 'store'])
            ->name('projects.previews.store');
        Route::get('projekte/{project}/vorschauen/{preview}/bearbeiten', [Admin\PreviewController::class, 'edit'])
            ->name('projects.previews.edit');
        Route::patch('projekte/{project}/vorschauen/{preview}', [Admin\PreviewController::class, 'update'])
            ->name('projects.previews.update');
        Route::delete('projekte/{project}/vorschauen/{preview}', [Admin\PreviewController::class, 'destroy'])
            ->name('projects.previews.destroy');
        // Status is the result of an action, never a form field.
        Route::post('projekte/{project}/vorschauen/{preview}/bereitstellen', [Admin\PreviewController::class, 'provision'])
            ->name('projects.previews.provision');
        Route::post('projekte/{project}/vorschauen/{preview}/deaktivieren', [Admin\PreviewController::class, 'disable'])
            ->name('projects.previews.disable');
    });

/*
|--------------------------------------------------------------------------
| Customer portal
|--------------------------------------------------------------------------
|
| Read only. Project and preview ids are resolved through the visibility scope,
| so anything the signed-in user may not see is simply "not found".
|
*/

Route::middleware('auth')
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {
        Route::get('/', Portal\DashboardController::class)->name('dashboard');
        Route::get('projekte/{project}', [Portal\ProjectController::class, 'show'])->name('projects.show');
        Route::get('projekte/{project}/vorschauen/{preview}', [Portal\ProjectController::class, 'showPreview'])
            ->name('previews.show');
    });
