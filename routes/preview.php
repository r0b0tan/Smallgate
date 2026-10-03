<?php

use App\Http\Controllers\PreviewHostController;
use App\Http\Middleware\SecurePreviewHostResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Preview hosts
|--------------------------------------------------------------------------
|
| One label below config('previews.base_domain'), e.g.
| holzmann.clickit-preview.de -- registered in bootstrap/app.php. See
| docs/adr/0003-preview-delivery.md.
|
| Deliberately outside the "web" group: no portal session, no CSRF token, no
| encrypted cookies, no cookie at all but the preview session's own.
|
| Registered before the portal's routes and covering every path and method,
| so nothing on a preview host ever falls through to a portal route. Only GET
| and HEAD are served; a static draft has nothing to post to.
|
*/

Route::middleware(SecurePreviewHostResponse::class)
    ->name('preview-host.')
    ->group(function () {
        // Below the reserved prefix PreviewFileResolver never serves. The
        // token is the only secret here, so the exchange is throttled.
        Route::get('__smallgate/zugang', [PreviewHostController::class, 'enter'])
            ->middleware('throttle:preview-handoff')
            ->name('enter');

        Route::get('{path?}', [PreviewHostController::class, 'show'])
            ->where('path', '.*')
            ->name('show');

        Route::match(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '{path?}', [PreviewHostController::class, 'refuse'])
            ->where('path', '.*')
            ->name('refuse');
    });
