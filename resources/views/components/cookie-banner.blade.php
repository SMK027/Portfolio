{{--
    Bandeau de consentement aux cookies (site public).
    Le choix est mémorisé 13 mois dans le cookie « cookie_consent » (accepted / refused).
    TODO : phase de développement — la mesure d'audience (App\Http\Middleware\RecordPageView)
    collecte encore les données quel que soit le choix ; à faire respecter avant la mise en production.
--}}
<div x-data="{
        open: ! document.cookie.split('; ').some(c => c.startsWith('cookie_consent=')),
        choose(value) {
            document.cookie = 'cookie_consent=' + value + '; max-age=' + (60 * 60 * 24 * 395) + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
            this.open = false;
        },
     }"
     @cookie-consent-open.window="open = true"
     x-show="open" x-cloak x-transition.opacity
     role="dialog" aria-live="polite" aria-labelledby="cookie-banner-title"
     class="fixed inset-x-0 bottom-0 z-50 p-4 sm:p-6">
    <div class="mx-auto flex max-w-4xl flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-xl sm:flex-row sm:items-center">
        <div class="flex-1 text-sm text-slate-600">
            <p id="cookie-banner-title" class="font-medium text-slate-900">Cookies et mesure d'audience</p>
            <p class="mt-1">
                Ce site utilise des cookies pour mesurer son audience (pages vues, durée de lecture, pays) et reconnaître un visiteur qui revient.
                Aucune donnée n'est transmise à un service tiers. Vous pouvez accepter ou refuser, et changer d'avis à tout moment depuis le pied de page.
            </p>
        </div>
        <div class="flex flex-none gap-2">
            <button type="button" class="btn-secondary" @click="choose('refused')">Refuser</button>
            <button type="button" class="btn-primary" @click="choose('accepted')">Accepter</button>
        </div>
    </div>
</div>
