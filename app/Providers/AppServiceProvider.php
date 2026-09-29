<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Page;
use App\Models\Profile;
use App\Models\User;
use App\Services\EditorJsRenderer;
use App\Services\Recaptcha;
use Illuminate\Support\Carbon;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        Gate::define('manage-users', fn (User $user) => $user->isSuperAdmin());

        // Menu et présentation partagés par toutes les pages publiques.
        View::composer('layouts.public', function ($view) {
            $user = auth()->user();

            $view->with([
                'navPages'      => Page::visibleTo($user)->where('key', '!=', 'home'),
                'homePage'      => Page::findByKey('home'),
                'siteProfile'   => Profile::current(),
                'isAdmin'       => (bool) $user?->isAdmin(),
                'announcements' => Announcement::visible()->ordered()->get(),
            ]);
        });

        // @editorjs($article->content) : rendu HTML sûr d'un contenu Editor.js
        Blade::directive('editorjs', fn ($expression) => "<?php echo app(\App\Services\EditorJsRenderer::class)->render({$expression}); ?>");
    }
}
