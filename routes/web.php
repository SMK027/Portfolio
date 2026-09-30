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
Route::get('/experiences', [BackgroundController::class, 'experiences'])->middleware('page:experiences')->name('experiences');
Route::get('/loisirs', [BackgroundController::class, 'hobbies'])->middleware('page:loisirs')->name('loisirs');

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
});

// Pièces jointes d'articles : accessibles au public selon la page Veille et la
// publication, et aux rédacteurs (y compris brouillons) — voir ArticleFileController.
Route::get('/veille/{article:id}/fichiers/{file}', [ArticleFileController::class, 'show'])
    ->scopeBindings()
    ->name('articles.files.show');

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
    $user = auth()->user();

    return match (true) {
        $user->isAdmin()          => redirect()->route('admin.dashboard'),
        $user->isBot()            => redirect(collect([
            $user->canWriteArticles() ? route('admin.articles.index') : null,
            $user->hasBotPermission('projects.read|projects.write') ? route('admin.projets.index') : null,
            $user->hasBotPermission('announcements.read|announcements.write') ? route('admin.annonces.index') : null,
            $user->hasBotPermission('messages.read') ? route('admin.messages.index') : null,
            $user->hasBotPermission('content.export|content.import') ? route('admin.transfer.index') : null,
            $user->hasBotPermission('maintenance.manage') ? route('admin.maintenance.edit') : null,
        ])->filter()->first() ?? route('bot.idle')),
        $user->canWriteArticles() => redirect()->route('admin.articles.index'),
        default                   => redirect()->route('profile.edit'),
    };
})->middleware('auth')->name('dashboard');

// Bot sans aucune autorisation : page d'information
Route::get('/admin/aucun-acces', fn () => view('admin.bot-idle'))->middleware('auth')->name('bot.idle');

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

    Route::get('/referencement', [Admin\SeoController::class, 'edit'])->name('seo.edit');
    Route::put('/referencement', [Admin\SeoController::class, 'update'])->name('seo.update');

    Route::get('/pages', [Admin\PageController::class, 'index'])->name('pages.index');
    Route::put('/pages', [Admin\PageController::class, 'update'])->name('pages.update');

    Route::resource('formations', Admin\EducationController::class)
        ->except('show')->parameters(['formations' => 'education']);
    Route::resource('experiences', Admin\ExperienceController::class)->except('show');
    Route::resource('loisirs', Admin\HobbyController::class)
        ->except('show')->parameters(['loisirs' => 'hobby']);
    Route::resource('diplomes', Admin\DiplomaController::class)
        ->except('show')->parameters(['diplomes' => 'diploma']);
    Route::resource('certifications', Admin\CertificationController::class)->except('show');
    Route::resource('competences', Admin\SkillController::class)
        ->except('show')->parameters(['competences' => 'skill']);
    Route::post('/themes/rapide', [Admin\ThemeController::class, 'quickStore'])->name('themes.quick');
    Route::resource('themes', Admin\ThemeController::class)->except('show');

    Route::delete('/messages/{message}', [Admin\ContactMessageController::class, 'destroy'])->name('messages.destroy');

    // Comptes de service et leurs autorisations : super-administrateurs uniquement
    Route::middleware('can:manage-service-accounts')->group(function () {
        Route::resource('comptes-service', Admin\ServiceAccountController::class)
            ->parameters(['comptes-service' => 'serviceAccount'])->names('service-accounts');
        Route::post('/comptes-service/{serviceAccount}/codes', [Admin\ServiceAccountController::class, 'issueToken'])->name('service-accounts.tokens.store');
        Route::patch('/comptes-service/{serviceAccount}/codes/{token}', [Admin\ServiceAccountController::class, 'toggleToken'])->name('service-accounts.tokens.toggle');
        Route::delete('/comptes-service/{serviceAccount}/codes/{token}', [Admin\ServiceAccountController::class, 'destroyToken'])->name('service-accounts.tokens.destroy');
    });

    Route::get('/journal', [Admin\AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/journal/{log}', [Admin\AuditLogController::class, 'show'])->name('audit.show');

    Route::resource('utilisateurs', Admin\UserController::class)
        ->except('show')->parameters(['utilisateurs' => 'user']);

});

/*
|--------------------------------------------------------------------------
| Sections accessibles aux bots selon leurs autorisations
|--------------------------------------------------------------------------
| « can:panel,<autorisation> » : administrateurs, ou bots disposant de
| l'autorisation (plusieurs possibles, séparées par « | »).
*/

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    // Guillemets : sans eux, le middleware « can » y verrait un nom de paramètre de route.
    $panel = fn (string $permissions) => "can:panel,'{$permissions}'";

    Route::resource('projets', Admin\ProjectController::class)
        ->except('show')->parameters(['projets' => 'project'])
        ->middlewareFor(['index', 'edit'], $panel('projects.read|projects.write'))
        ->middlewareFor(['create', 'store', 'update'], $panel('projects.write'))
        ->middlewareFor('destroy', $panel('projects.delete'));
    Route::delete('/projets/{project}/fichiers/{file}', [Admin\ProjectController::class, 'destroyFile'])
        ->scopeBindings()->middleware($panel('projects.write'))->name('projets.files.destroy');

    Route::resource('annonces', Admin\AnnouncementController::class)
        ->except('show')->parameters(['annonces' => 'announcement'])
        ->middlewareFor(['index', 'edit'], $panel('announcements.read|announcements.write'))
        ->middlewareFor(['create', 'store', 'update', 'destroy'], $panel('announcements.write'));

    Route::get('/messages', [Admin\ContactMessageController::class, 'index'])->middleware($panel('messages.read'))->name('messages.index');
    Route::get('/messages/{message}', [Admin\ContactMessageController::class, 'show'])->middleware($panel('messages.read'))->name('messages.show');

    Route::get('/maintenance', [Admin\MaintenanceController::class, 'edit'])->middleware($panel('maintenance.manage'))->name('maintenance.edit');
    Route::put('/maintenance', [Admin\MaintenanceController::class, 'update'])->middleware($panel('maintenance.manage'))->name('maintenance.update');

    Route::prefix('import-export')->name('transfer.')->controller(Admin\TransferController::class)->group(function () use ($panel) {
        Route::get('/', 'index')->middleware($panel('content.export|content.import'))->name('index');
        Route::post('/export', 'export')->middleware($panel('content.export'))->name('export');
        Route::get('/modele', 'example')->middleware($panel('content.import'))->name('example');
        Route::post('/simulation', 'preview')->middleware($panel('content.import'))->name('preview');
        Route::post('/import', 'import')->middleware($panel('content.import'))->name('import');
        Route::delete('/import', 'cancel')->middleware($panel('content.import'))->name('cancel');
    });
});

/*
|--------------------------------------------------------------------------
| Rédaction d'articles : administrateurs et contributeurs
|--------------------------------------------------------------------------
| Les droits fins (modifier, publier, valider, supprimer) sont vérifiés par
| App\Policies\ArticlePolicy.
*/

Route::middleware(['auth', 'can:write-articles'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('articles', Admin\ArticleController::class);
    Route::post('/articles/{article}/valider', [Admin\ArticleController::class, 'approve'])->name('articles.approve');
    Route::post('/articles/{article}/renvoyer', [Admin\ArticleController::class, 'requestChanges'])->name('articles.request-changes');
});

Route::middleware(['auth', 'can:use-editor'])->prefix('admin')->name('admin.')->group(function () {
    // Outils de l'éditeur : illustrations et conversions visuel ⇄ Markdown
    Route::post('/uploads/image', Admin\EditorUploadController::class)
        ->middleware('throttle:60,1')
        ->name('uploads.image');
    Route::post('/uploads/image-url', Admin\EditorImageUrlController::class)
        ->middleware('throttle:120,1')
        ->name('uploads.image-url');
    Route::post('/editeur/vers-markdown', [Admin\EditorConversionController::class, 'toMarkdown'])
        ->middleware('throttle:120,1')->name('editor.to-markdown');
    Route::post('/editeur/vers-blocs', [Admin\EditorConversionController::class, 'toBlocks'])
        ->middleware('throttle:240,1')->name('editor.to-blocks');
});

require __DIR__.'/auth.php';
