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
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site public
|--------------------------------------------------------------------------
| Chaque page est protégée par le middleware "page:{clé}" : si la page est
| privée, seuls les administrateurs connectés peuvent y accéder.
*/

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::post('/stats/duree', \App\Http\Controllers\PageViewDurationController::class)->middleware('throttle:120,1')->name('stats.duration');
Route::get('/cv', \App\Http\Controllers\CvController::class)->middleware('throttle:30,1')->name('cv.download');

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

// Brouillon partagé (lien secret et temporaire), indépendant de la visibilité de la page Veille
Route::get('/veille/apercu/{token}', [ArticleController::class, 'preview'])
    ->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:30,1')->name('articles.preview');

// Pièces jointes d'articles : accessibles au public selon la page Veille et la
// publication, et aux rédacteurs (y compris brouillons) — voir ArticleFileController.
Route::get('/veille/{article:id}/fichiers/{file}', [ArticleFileController::class, 'show'])
    ->scopeBindings()
    ->name('articles.files.show');

Route::get('/recherche', \App\Http\Controllers\SearchController::class)->middleware('throttle:60,1')->name('search');

Route::middleware('page:rendez-vous')->prefix('rendez-vous')->name('appointments.')->group(function () {
    Route::get('/', [\App\Http\Controllers\AppointmentController::class, 'show'])->name('show');
    Route::post('/', [\App\Http\Controllers\AppointmentController::class, 'store'])->middleware('throttle:5,10')->name('store');
});
// Annulation par le visiteur : lien personnel reçu par e-mail (indépendant de la visibilité de la page)
Route::get('/rendez-vous/annuler/{token}', [\App\Http\Controllers\AppointmentController::class, 'cancelForm'])
    ->where('token', '[A-Za-z0-9]{48}')->name('appointments.cancel');
Route::post('/rendez-vous/annuler/{token}', [\App\Http\Controllers\AppointmentController::class, 'cancel'])
    ->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:10,1');

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
        $user->hasLimitedAccess() => redirect()->route(\App\Support\PanelSections::firstRouteFor($user) ?? 'bot.idle'),
        $user->canWriteArticles() => redirect()->route('admin.articles.index'),
        default                   => redirect()->route('profile.edit'),
    };
})->middleware('auth')->name('dashboard');

// Bot ou personnel sans aucune autorisation : page d'information
Route::get('/admin/aucun-acces', fn () => view('admin.bot-idle'))->middleware('auth')->name('bot.idle');

// Validation superviseur d'une opération non habilitée
Route::middleware('auth')->prefix('supervision')->name('supervision.')->controller(\App\Http\Controllers\SupervisionController::class)->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
    Route::delete('/', 'destroy')->name('destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Double authentification du compte : personnes uniquement
Route::middleware(['auth', 'can:use-two-factor'])->prefix('profile/double-authentification')->name('two-factor.')
    ->controller(\App\Http\Controllers\TwoFactorController::class)->group(function () {
        Route::post('/application', 'startTotp')->name('totp.start');
        Route::put('/application', 'confirmTotp')->middleware('throttle:10,1')->name('totp.confirm');
        Route::delete('/application/annuler', 'cancelTotp')->name('totp.cancel');
        Route::delete('/application', 'disableTotp')->name('totp.disable');
        Route::post('/cles/options', 'keyOptions')->name('keys.options');
        Route::post('/cles', 'storeKey')->middleware('throttle:10,1')->name('keys.store');
        Route::delete('/cles/{key}', 'destroyKey')->name('keys.destroy');
        Route::post('/codes-de-secours', 'regenerateRecoveryCodes')->name('recovery-codes');
    });

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::post('/email-test', Admin\MailTestController::class)
        ->middleware('throttle:5,1')
        ->name('mail.test');

    // Comptes de service et leurs autorisations : super-administrateurs uniquement
    Route::middleware('can:manage-service-accounts')->group(function () {
        Route::resource('comptes-service', Admin\ServiceAccountController::class)
            ->parameters(['comptes-service' => 'serviceAccount'])->names('service-accounts');
        Route::post('/comptes-service/{serviceAccount}/codes', [Admin\ServiceAccountController::class, 'issueToken'])->name('service-accounts.tokens.store');
        Route::patch('/comptes-service/{serviceAccount}/codes/{token}', [Admin\ServiceAccountController::class, 'toggleToken'])->name('service-accounts.tokens.toggle');
        Route::delete('/comptes-service/{serviceAccount}/codes/{token}', [Admin\ServiceAccountController::class, 'destroyToken'])->name('service-accounts.tokens.destroy');
    });

    // Adresse de la page de connexion : super-administrateurs uniquement
    Route::middleware('can:manage-login-path')->group(function () {
        Route::get('/adresse-connexion', [Admin\LoginPathController::class, 'edit'])->name('login-path.edit');
        Route::put('/adresse-connexion', [Admin\LoginPathController::class, 'update'])->middleware('throttle:10,1')->name('login-path.update');
    });

    // Adresses IP bannies après des échecs de connexion : super-administrateurs uniquement
    Route::resource('ip-bannies', Admin\IpBanController::class)
        ->only(['index', 'edit', 'update', 'destroy'])
        ->parameters(['ip-bannies' => 'ipBan'])->names('ip-bans')
        ->middleware('can:manage-ip-bans');

    // Superviseurs : super-administrateurs uniquement
    Route::resource('superviseurs', Admin\SupervisorController::class)->except('show')
        ->parameters(['superviseurs' => 'supervisor'])->names('supervisors')->middleware('can:manage-service-accounts');

    Route::get('/journal', [Admin\AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/journal/{log}', [Admin\AuditLogController::class, 'show'])->name('audit.show');
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

    // Contenu à lecture / écriture / suppression : la page de modification sert
    // aussi de consultation (formulaire en lecture seule sans « .write »).
    $crud = function (string $uri, string $controller, string $permission, array $parameters = []) use ($panel) {
        return Route::resource($uri, $controller)->except('show')->parameters($parameters)
            ->middlewareFor(['index', 'edit'], $panel("{$permission}.read|{$permission}.write|{$permission}.delete"))
            ->middlewareFor(['create', 'store', 'update'], $panel("{$permission}.write"))
            ->middlewareFor('destroy', $panel("{$permission}.delete"));
    };

    Route::get('/presentation/cv', [\App\Http\Controllers\CvController::class, 'preview'])->middleware($panel('profile.read|profile.write'))->name('profile.cv');
    Route::get('/presentation', [Admin\ProfileController::class, 'edit'])->middleware($panel('profile.read|profile.write'))->name('profile.edit');
    Route::put('/presentation', [Admin\ProfileController::class, 'update'])->middleware($panel('profile.write'))->name('profile.update');

    $crud('formations', Admin\EducationController::class, 'educations', ['formations' => 'education']);
    $crud('experiences', Admin\ExperienceController::class, 'experiences');
    $crud('diplomes', Admin\DiplomaController::class, 'diplomas', ['diplomes' => 'diploma']);
    $crud('certifications', Admin\CertificationController::class, 'certifications');
    $crud('competences', Admin\SkillController::class, 'skills', ['competences' => 'skill']);
    $crud('loisirs', Admin\HobbyController::class, 'hobbies', ['loisirs' => 'hobby']);
    Route::post('/themes/rapide', [Admin\ThemeController::class, 'quickStore'])->middleware($panel('themes.write'))->name('themes.quick');
    $crud('themes', Admin\ThemeController::class, 'themes');

    $crud('projets', Admin\ProjectController::class, 'projects', ['projets' => 'project']);
    Route::delete('/projets/{project}/fichiers/{file}', [Admin\ProjectController::class, 'destroyFile'])
        ->scopeBindings()->middleware($panel('projects.write'))->name('projets.files.destroy');

    $crud('annonces', Admin\AnnouncementController::class, 'announcements', ['annonces' => 'announcement']);

    Route::get('/messages', [Admin\ContactMessageController::class, 'index'])->middleware($panel('messages.read'))->name('messages.index');
    Route::get('/messages/{message}', [Admin\ContactMessageController::class, 'show'])->middleware($panel('messages.read'))->name('messages.show');
    Route::patch('/messages/{message}/non-lu', [Admin\ContactMessageController::class, 'markUnread'])->middleware($panel('messages.read'))->name('messages.unread');
    Route::delete('/messages/{message}', [Admin\ContactMessageController::class, 'destroy'])->middleware($panel('messages.delete'))->name('messages.destroy');

    Route::get('/rendez-vous', [Admin\AppointmentController::class, 'index'])->middleware($panel('appointments.read|appointments.write'))->name('appointments.index');
    Route::put('/rendez-vous/{appointment}/decision', [Admin\AppointmentController::class, 'decide'])->middleware($panel('appointments.write'))->name('appointments.decide');
    Route::delete('/rendez-vous/{appointment}', [Admin\AppointmentController::class, 'destroy'])->middleware($panel('appointments.delete'))->name('appointments.destroy');
    Route::get('/rendez-vous/disponibilites', [Admin\AppointmentController::class, 'editAvailability'])->middleware($panel('appointments.read|appointments.write'))->name('appointments.availability');
    Route::put('/rendez-vous/disponibilites', [Admin\AppointmentController::class, 'updateSettings'])->middleware($panel('appointments.write'))->name('appointments.availability.update');
    Route::get('/rendez-vous/disponibilites/evenements', [Admin\AppointmentController::class, 'events'])->middleware($panel('appointments.read|appointments.write'))->name('appointments.availability.events');
    Route::post('/rendez-vous/disponibilites/plages', [Admin\AppointmentController::class, 'storeRange'])->middleware($panel('appointments.write'))->name('appointments.availability.ranges.store');
    Route::put('/rendez-vous/disponibilites/plages/{kind}/{id}', [Admin\AppointmentController::class, 'updateRange'])->whereNumber('id')->middleware($panel('appointments.write'))->name('appointments.availability.ranges.update');
    Route::delete('/rendez-vous/disponibilites/plages/{kind}/{id}', [Admin\AppointmentController::class, 'destroyRange'])->whereNumber('id')->middleware($panel('appointments.write'))->name('appointments.availability.ranges.destroy');
    Route::post('/rendez-vous/disponibilites/blocages', [Admin\AppointmentController::class, 'storeBlock'])->middleware($panel('appointments.write'))->name('appointments.availability.blocks.store');
    Route::delete('/rendez-vous/disponibilites/blocages/{block}', [Admin\AppointmentController::class, 'destroyBlock'])->middleware($panel('appointments.write'))->name('appointments.availability.blocks.destroy');
    Route::post('/rendez-vous/disponibilites/fermetures', [Admin\AppointmentController::class, 'toggleClosure'])->middleware($panel('appointments.write'))->name('appointments.availability.closures.toggle');

    Route::get('/pages', [Admin\PageController::class, 'index'])->middleware($panel('pages.read|pages.write'))->name('pages.index');
    Route::put('/pages', [Admin\PageController::class, 'update'])->middleware($panel('pages.write'))->name('pages.update');

    Route::get('/referencement', [Admin\SeoController::class, 'edit'])->middleware($panel('seo.read|seo.write'))->name('seo.edit');
    Route::put('/referencement', [Admin\SeoController::class, 'update'])->middleware($panel('seo.write'))->name('seo.update');

    Route::get('/statistiques', Admin\StatisticsController::class)->middleware($panel('stats.read'))->name('statistics');

    Route::get('/maintenance', [Admin\MaintenanceController::class, 'edit'])->middleware($panel('maintenance.read|maintenance.manage'))->name('maintenance.edit');
    Route::put('/maintenance', [Admin\MaintenanceController::class, 'update'])->middleware($panel('maintenance.manage'))->name('maintenance.update');

    // Comptes : consultation par les admins ; création, modification et suppression
    // par les super-admins, ou par les bots autorisés pour les contributeurs (voir UserController).
    Route::resource('utilisateurs', Admin\UserController::class)
        ->except('show')->parameters(['utilisateurs' => 'user'])
        ->middlewareFor('index', $panel('users.read|users.write|users.delete'))
        ->middlewareFor(['create', 'store', 'edit', 'update'], $panel('users.write'))
        ->middlewareFor('destroy', $panel('users.delete'));
    Route::delete('/utilisateurs/{user}/double-authentification', [Admin\UserController::class, 'resetTwoFactor'])->name('utilisateurs.two-factor.reset');

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
    Route::get('/articles/{article}/versions', [Admin\ArticleRevisionController::class, 'index'])->name('articles.revisions.index');
    Route::get('/articles/{article}/versions/{revision}', [Admin\ArticleRevisionController::class, 'show'])->name('articles.revisions.show');
    Route::post('/articles/{article}/versions/{revision}/restaurer', [Admin\ArticleRevisionController::class, 'restore'])->name('articles.revisions.restore');
    Route::post('/articles/{article}/apercu', [Admin\ArticleController::class, 'sharePreview'])->name('articles.preview.store');
    Route::delete('/articles/{article}/apercu', [Admin\ArticleController::class, 'revokePreview'])->name('articles.preview.destroy');
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
