<x-app-layout>
    <x-slot name="title">Tableau de bord</x-slot>
    <x-slot name="header">Tableau de bord</x-slot>
    <x-slot name="actions">
        <a href="{{ route('admin.projets.create') }}" class="btn-secondary hidden sm:inline-flex"><x-icon name="plus" class="h-4 w-4" /> Projet</a>
        <a href="{{ route('admin.articles.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> Article</a>
    </x-slot>

    @if ($mailError = app(\App\Services\SafeMailer::class)->lastError())
        <div class="flex flex-col gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 sm:flex-row sm:items-center">
            <x-icon name="mail" class="h-5 w-5 flex-none" />
            <div class="min-w-0 flex-1">
                <p><strong>Les e-mails ne sont pas envoyés.</strong> Le site continue de fonctionner et les messages de contact restent enregistrés ci-dessous.</p>
                <p class="mt-1 break-words text-xs text-red-700">
                    Dernière erreur @if ($mailError['at'])({{ \Illuminate\Support\Carbon::parse($mailError['at'])->translatedFormat('j F Y à H:i') }})@endif : {{ $mailError['message'] }}
                </p>
                <p class="mt-1 text-xs text-red-700">Vérifiez les paramètres <code>MAIL_*</code> du fichier <code>.env</code>, puis relancez un test.</p>
            </div>
            <form method="POST" action="{{ route('admin.mail.test') }}" class="flex-none">
                @csrf
                <button class="btn-secondary btn-sm">Envoyer un e-mail de test</button>
            </form>
        </div>
    @endif

    @if (app(\App\Services\Maintenance::class)->isActive())
        <a href="{{ route('admin.maintenance.edit') }}" class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 hover:bg-amber-100">
            <x-icon name="wrench" class="h-5 w-5 flex-none" />
            <span>
                <strong>Maintenance active</strong> : les visiteurs voient la page de maintenance.
                @if ($end = app(\App\Services\Maintenance::class)->endsAt())
                    Fin automatique le {{ $end->translatedFormat('j F Y à H:i') }}.
                @endif
            </span>
            <x-icon name="arrow-right" class="ml-auto h-4 w-4 flex-none" />
        </a>
    @endif

    @unless (\App\Models\Setting::siteIsIndexable())
        <a href="{{ route('admin.seo.edit') }}" class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 hover:bg-red-100">
            <x-icon name="globe" class="h-5 w-5 flex-none" />
            <span><strong>Le site est désindexé</strong> : les moteurs de recherche ne le référencent pas. Modifier ce réglage</span>
            <x-icon name="arrow-right" class="ml-auto h-4 w-4 flex-none" />
        </a>
    @endunless

    @if ($pendingArticles->isNotEmpty())
        <x-admin.section title="Articles à valider" description="Soumis par des contributeurs : relisez-les puis publiez-les ou renvoyez-les en brouillon.">
            @foreach ($pendingArticles as $pending)
                <a href="{{ route('admin.articles.show', $pending) }}" class="-mx-2 flex items-center justify-between gap-3 rounded-lg px-2 py-2 hover:bg-slate-50">
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-slate-800">{{ $pending->title }}</span>
                        <span class="block text-xs text-slate-500">{{ $pending->author?->name }} · soumis {{ $pending->submitted_at?->diffForHumans() }}</span>
                    </span>
                    <span class="btn-secondary btn-sm flex-none">Relire</span>
                </a>
            @endforeach
        </x-admin.section>
    @endif

    <a href="{{ route('admin.statistics', ['periode' => 7]) }}" class="card flex flex-wrap items-center justify-between gap-4 p-5 transition hover:border-primary-200 hover:shadow-md">
        <div>
            <p class="text-sm text-slate-500">Visites des 7 derniers jours</p>
            <p class="mt-1 font-display text-2xl font-bold text-slate-900">{{ $visits['views'] }} pages vues · {{ $visits['visitors'] }} visiteurs</p>
        </div>
        <div class="flex h-12 items-end gap-1" aria-hidden="true">
            @php $peak = max(1, $visits['series']->max('views')); @endphp
            @foreach ($visits['series'] as $day)
                <div class="w-3 rounded-t bg-primary-400" style="height: {{ max(2, $day['views'] / $peak * 100) }}%"></div>
            @endforeach
        </div>
        <span class="text-sm font-semibold text-primary-600">Statistiques détaillées →</span>
    </a>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($stats as $stat)
            <a href="{{ route($stat['route']) }}" class="card p-5 transition hover:border-primary-200 hover:shadow-md">
                <p class="text-sm text-slate-500">{{ $stat['label'] }}</p>
                <p class="mt-1 font-display text-3xl font-bold text-slate-900">{{ $stat['count'] }}</p>
            </a>
        @endforeach
        <a href="{{ route('admin.messages.index') }}" class="card p-5 transition hover:border-primary-200 hover:shadow-md {{ $unreadCount ? 'border-primary-200 bg-primary-50' : '' }}">
            <p class="text-sm text-slate-500">Messages non lus</p>
            <p class="mt-1 font-display text-3xl font-bold {{ $unreadCount ? 'text-primary-700' : 'text-slate-900' }}">{{ $unreadCount }}</p>
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-admin.section title="Derniers messages">
            @forelse ($latestMessages as $message)
                <a href="{{ route('admin.messages.show', $message) }}" class="-mx-2 flex items-start gap-3 rounded-lg px-2 py-2 hover:bg-slate-50">
                    <span class="mt-1.5 h-2 w-2 flex-none rounded-full {{ $message->read_at ? 'bg-slate-200' : 'bg-primary-500' }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm {{ $message->read_at ? 'text-slate-700' : 'font-semibold text-slate-900' }}">{{ $message->subject }}</span>
                        <span class="block truncate text-xs text-slate-500">{{ $message->fullName() }} · {{ $message->created_at->diffForHumans() }}</span>
                    </span>
                </a>
            @empty
                <p class="text-sm text-slate-500">Aucun message reçu.</p>
            @endforelse
        </x-admin.section>

        <div class="space-y-6">
            <x-admin.section title="Brouillons et articles programmés">
                @forelse ($drafts as $draft)
                    <a href="{{ route('admin.articles.edit', $draft) }}" class="-mx-2 flex items-center justify-between gap-3 rounded-lg px-2 py-2 hover:bg-slate-50">
                        <span class="truncate text-sm text-slate-700">{{ $draft->title }}</span>
                        <span class="badge-amber flex-none">{{ $draft->status() }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500">Aucun brouillon.</p>
                @endforelse
            </x-admin.section>

            <x-admin.section title="Pages privées">
                @forelse ($privatePages as $privatePage)
                    <p class="flex items-center gap-2 text-sm text-slate-700"><x-icon name="lock" class="h-4 w-4 text-amber-500" /> {{ $privatePage->title }}</p>
                @empty
                    <p class="text-sm text-slate-500">Toutes les pages sont publiques.</p>
                @endforelse
                <a href="{{ route('admin.pages.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-primary-700">Gérer la visibilité <x-icon name="arrow-right" class="h-4 w-4" /></a>
            </x-admin.section>
        </div>
    </div>
</x-app-layout>
