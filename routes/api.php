<?php

use App\Http\Controllers\Api;
use App\Support\ServicePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API des comptes de service
|--------------------------------------------------------------------------
| Authentification : « Authorization: Bearer <code d'application> ».
| Chaque route exige une autorisation (service.can:…) attribuée par un
| super-administrateur. Toutes les modifications sont journalisées.
*/

Route::prefix('v1')->middleware('auth.service')->group(function () {
    Route::get('/me', fn (Request $request) => response()->json(['data' => [
        'name'        => $request->user()->name,
        'description' => $request->user()->description,
        'permissions' => collect($request->user()->permissions ?? [])->mapWithKeys(fn ($p) => [$p => ServicePermissions::label($p)]),
        'token'       => $request->attributes->get('service_token')->name,
    ]]));

    // Articles de veille
    Route::get('/articles', [Api\ArticleController::class, 'index'])->middleware('service.can:articles.read');
    Route::get('/articles/{article:id}', [Api\ArticleController::class, 'show'])->middleware('service.can:articles.read');
    Route::post('/articles', [Api\ArticleController::class, 'store'])->middleware('service.can:articles.write');
    Route::patch('/articles/{article:id}', [Api\ArticleController::class, 'update'])->middleware('service.can:articles.write');
    Route::post('/articles/{article:id}/publish', [Api\ArticleController::class, 'publish'])->middleware('service.can:articles.publish');
    Route::post('/articles/{article:id}/unpublish', [Api\ArticleController::class, 'unpublish'])->middleware('service.can:articles.publish');
    Route::delete('/articles/{article:id}', [Api\ArticleController::class, 'destroy'])->middleware('service.can:articles.delete');

    // Projets
    Route::get('/projects', [Api\ProjectController::class, 'index'])->middleware('service.can:projects.read');
    Route::get('/projects/{project:id}', [Api\ProjectController::class, 'show'])->middleware('service.can:projects.read');
    Route::post('/projects', [Api\ProjectController::class, 'store'])->middleware('service.can:projects.write');
    Route::patch('/projects/{project:id}', [Api\ProjectController::class, 'update'])->middleware('service.can:projects.write');
    Route::delete('/projects/{project:id}', [Api\ProjectController::class, 'destroy'])->middleware('service.can:projects.delete');

    // Annonces
    Route::get('/announcements', [Api\AnnouncementController::class, 'index'])->middleware('service.can:announcements.read');
    Route::get('/announcements/{announcement}', [Api\AnnouncementController::class, 'show'])->middleware('service.can:announcements.read');
    Route::post('/announcements', [Api\AnnouncementController::class, 'store'])->middleware('service.can:announcements.write');
    Route::patch('/announcements/{announcement}', [Api\AnnouncementController::class, 'update'])->middleware('service.can:announcements.write');
    Route::delete('/announcements/{announcement}', [Api\AnnouncementController::class, 'destroy'])->middleware('service.can:announcements.delete');

    // Messages de contact
    Route::get('/messages', [Api\MessageController::class, 'index'])->middleware('service.can:messages.read');
    Route::get('/messages/{message}', [Api\MessageController::class, 'show'])->middleware('service.can:messages.read');

    // Contenu
    Route::get('/export', [Api\ContentController::class, 'export'])->middleware('service.can:content.export');
    Route::post('/import', [Api\ContentController::class, 'import'])->middleware('service.can:content.import');

    // Maintenance
    Route::get('/maintenance', [Api\MaintenanceController::class, 'show'])->middleware('service.can:maintenance.read|maintenance.manage');
    Route::put('/maintenance', [Api\MaintenanceController::class, 'update'])->middleware('service.can:maintenance.manage');
});
