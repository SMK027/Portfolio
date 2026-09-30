<x-app-layout>
    <x-slot name="title">Référencement</x-slot>
    <x-slot name="header">Référencement</x-slot>

    <form method="POST" action="{{ route('admin.seo.update') }}" class="space-y-6" x-data="{ indexable: @js((bool) old('indexable', $indexable)) }">
        @csrf
        @method('PUT')
        <input type="hidden" name="indexable" :value="indexable ? 1 : 0">

        <x-admin.section title="Indexation par les moteurs de recherche">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl"
                          :class="indexable ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'">
                        <x-icon name="globe" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-medium text-slate-900" x-text="indexable ? 'Le site peut être indexé' : 'Le site est désindexé'"></p>
                        <p class="text-sm text-slate-500" x-show="indexable">Google, Bing et les autres moteurs peuvent référencer les pages publiques.</p>
                        <p class="text-sm text-slate-500" x-show="!indexable" x-cloak>Les moteurs de recherche sont invités à ne pas référencer le site et à retirer les pages déjà indexées.</p>
                    </div>
                </div>
                <button type="button" role="switch" :aria-checked="indexable.toString()" @click="indexable = !indexable"
                        :class="indexable ? 'bg-emerald-500' : 'bg-slate-300'"
                        class="relative inline-flex h-7 w-12 flex-none items-center rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
                    <span class="sr-only">Autoriser l'indexation</span>
                    <span :class="indexable ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition"></span>
                </button>
            </div>
            @error('indexable')<p class="form-error">{{ $message }}</p>@enderror

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                <p class="font-medium text-slate-800">Quand le site est désindexé :</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>chaque page publique contient la balise <code class="rounded bg-white px-1">&lt;meta name="robots" content="noindex, nofollow"&gt;</code> ;</li>
                    <li>toutes les réponses (pages, PDF, documents, images des projets et articles) portent l'en-tête <code class="rounded bg-white px-1">X-Robots-Tag: noindex, nofollow</code> ;</li>
                    <li>le fichier <a href="{{ route('robots') }}" target="_blank" class="font-medium text-primary-600 underline">robots.txt</a> bloque les images publiques (<code class="rounded bg-white px-1">/storage/</code>).</li>
                </ul>
                <p class="mt-3">
                    Les pages restent volontairement explorables : un moteur doit pouvoir les lire pour découvrir la consigne et les retirer de ses résultats.
                    Le retrait prend de quelques jours à quelques semaines ; pour l'accélérer, utilisez l'outil de suppression d'URL de la
                    <a href="https://search.google.com/search-console" target="_blank" rel="noopener" class="font-medium text-primary-600 underline">Google Search Console</a>.
                </p>
                <p class="mt-2">Le site reste accessible aux visiteurs. Pour masquer une page, utilisez <a href="{{ route('admin.pages.index') }}" class="font-medium text-primary-600 underline">Pages & visibilité</a>.</p>
            </div>
        </x-admin.section>

        <x-admin.form-actions can="seo.write" />
    </form>
</x-app-layout>
