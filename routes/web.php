<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticleFileController;
use App\Http\Controllers\BackgroundController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\RobotsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site public
|--------------------------------------------------------------------------
| Chaque page est protégée par le middleware "page:{clé}" : si la page est
| privée, seuls les administrateurs connectés peuvent y accéder.
*/

Route::get('/robots.txt', RobotsController::class)->name('robots');

Route::get('/', HomeController::class)->middleware('page:home')->name('home');

Route::get('/formations', [BackgroundController::class, 'formations'])->middleware('page:formations')->name('formations');
Route::get('/diplomes', [BackgroundController::class, 'diplomas'])->middleware('page:diplomes')->name('diplomes');
Route::get('/certifications', [BackgroundController::class, 'certifications'])->middleware('page:certifications')->name('certifications');
Route::get('/competences', [BackgroundController::class, 'skills'])->middleware('page:competences')->name('competences');

Route::middleware('page:projets')->prefix('projets')->name('projects.')->group(function () {
    Route::get('/', [ProjectController::class, 'index'])->name('index');
    Route::get('/themes/{theme}', [ProjectController::class, 'theme'])->name('theme');
    Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
    Route::get('/{project:id}/fichiers/{file}', [ProjectFileController::class, 'show'])
        ->scopeBindings()
        ->name('files.show');
});

Route::middleware('page:veille')->prefix('veille')->name('articles.')->group(function () {
    Route::get('/', [ArticleController::class, 'index'])->name('index');
    Route::get('/{article}', [ArticleController::class, 'show'])->name('show');
    Route::get('/{article:id}/fichiers/{file}', [ArticleFileController::class, 'show'])
        ->scopeBindings()
        ->name('files.show');
});

Route::middleware('page:contact')->group(function () {
    Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:5,10')
        ->name('contact.store');
});

/*
|--------------------------------------------------------------------------
| Espace connecté
|--------------------------------------------------------------------------
*/

// Point d'entrée après connexion : back-office pour les admins, compte sinon.
Route::get('/dashboard', function () {
    return auth()->user()->isAdmin()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('profile.edit');
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/presentation', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/presentation', [Admin\ProfileController::class, 'update'])->name('profile.update');

    Route::post('/email-test', Admin\MailTestController::class)
        ->middleware('throttle:5,1')
        ->name('mail.test');

    Route::get('/maintenance', [Admin\MaintenanceController::class, 'edit'])->name('maintenance.edit');
    Route::put('/maintenance', [Admin\MaintenanceController::class, 'update'])->name('maintenance.update');

    Route::get('/referencement', [Admin\SeoController::class, 'edit'])->name('seo.edit');
    Route::put('/referencement', [Admin\SeoController::class, 'update'])->name('seo.update');

    Route::get('/pages', [Admin\PageController::class, 'index'])->name('pages.index');
    Route::put('/pages', [Admin\PageController::class, 'update'])->name('pages.update');

    Route::resource('formations', Admin\EducationController::class)
        ->except('show')->parameters(['formations' => 'education']);
    Route::resource('diplomes', Admin\DiplomaController::class)
        ->except('show')->parameters(['diplomes' => 'diploma']);
    Route::resource('certifications', Admin\CertificationController::class)->except('show');
    Route::resource('competences', Admin\SkillController::class)
        ->except('show')->parameters(['competences' => 'skill']);
    Route::resource('themes', Admin\ThemeController::class)->except('show');
    Route::resource('projets', Admin\ProjectController::class)
        ->except('show')->parameters(['projets' => 'project']);
    Route::delete('/projets/{project}/fichiers/{file}', [Admin\ProjectController::class, 'destroyFile'])
        ->scopeBindings()
        ->name('projets.files.destroy');
    Route::resource('articles', Admin\ArticleController::class)->except('show');

    Route::resource('annonces', Admin\AnnouncementController::class)
        ->except('show')->parameters(['annonces' => 'announcement']);

    Route::get('/messages', [Admin\ContactMessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{message}', [Admin\ContactMessageController::class, 'show'])->name('messages.show');
    Route::delete('/messages/{message}', [Admin\ContactMessageController::class, 'destroy'])->name('messages.destroy');

    Route::resource('utilisateurs', Admin\UserController::class)
        ->except('show')->parameters(['utilisateurs' => 'user']);

    // Téléversement d'illustrations depuis Editor.js
    Route::post('/uploads/image', Admin\EditorUploadController::class)
        ->middleware('throttle:60,1')
        ->name('uploads.image');
});

require __DIR__.'/auth.php';
