<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Page;
use App\Models\Profile;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\EditorJsRenderer;
use App\Services\Recaptcha;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Recaptcha::class);
        $this->app->singleton(EditorJsRenderer::class);
        $this->app->singleton(\App\Services\Maintenance::class);
        $this->app->singleton(AuditTrail::class);
    }

    /**
     * Événements d'authentification enregistrés dans le journal d'activité.
     */
    protected function registerAuditListeners(): void
    {
        $audit = fn () => app(AuditTrail::class);

        Event::listen(Login::class, fn (Login $e) => $audit()->record('auth.login', $e->user, meta: ['remember' => $e->remember], force: true));
        Event::listen(Logout::class, fn (Logout $e) => $e->user && $audit()->record('auth.logout', $e->user, force: true));
        Event::listen(Failed::class, fn (Failed $e) => $audit()->record('auth.failed', $e->user, meta: [
            'email' => Str::limit((string) ($e->credentials['email'] ?? ''), 100),
        ], force: true, label: $e->user ? null : Str::limit((string) ($e->credentials['email'] ?? ''), 100)));
        Event::listen(Lockout::class, fn (Lockout $e) => $audit()->record('auth.lockout', meta: [
            'email' => Str::limit((string) $e->request->input('email'), 100),
        ], force: true));
        Event::listen(PasswordReset::class, fn (PasswordReset $e) => $audit()->record('auth.password_reset', $e->user, force: true));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        Gate::define('manage-users', fn (User $user) => $user->isSuperAdmin());
        Gate::define('write-articles', fn (User $user) => $user->canWriteArticles());
        Gate::define('view-audit-log', fn (User $user) => $user->isSuperAdmin());

        $this->registerAuditListeners();

        // Menu et présentation partagés par toutes les pages publiques.
        View::composer('layouts.public', function ($view) {
            $user = auth()->user();

            $view->with([
                'navPages'      => Page::visibleTo($user)->where('key', '!=', 'home'),
                'homePage'      => Page::findByKey('home'),
                'siteProfile'   => Profile::current(),
                'isAdmin'       => (bool) $user?->isAdmin(),
                'announcements' => Announcement::visible()->ordered()->get(),
                'indexable'     => Setting::siteIsIndexable(),
                'maintenance'   => app(\App\Services\Maintenance::class)->isActive(),
            ]);
        });

        // @editorjs($article->content) : rendu HTML sûr d'un contenu Editor.js
        Blade::directive('editorjs', fn ($expression) => "<?php echo app(\App\Services\EditorJsRenderer::class)->render({$expression}); ?>");
    }
}
