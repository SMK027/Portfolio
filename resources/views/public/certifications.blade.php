<x-public-layout :page="$page" :title="$page->title">
    <x-page-header :page="$page" />

    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        @if ($certifications->isEmpty())
            <x-empty-state icon="badge" message="Aucune certification n'a encore été ajoutée." />
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($certifications as $certification)
                    <article class="card flex flex-col p-6">
                        <div class="flex items-start gap-4">
                            @if ($certification->badgeUrl())
                                <img src="{{ $certification->badgeUrl() }}" alt="Badge {{ $certification->name }}" class="h-16 w-16 flex-none rounded-xl object-contain">
                            @else
                                <span class="flex h-16 w-16 flex-none items-center justify-center rounded-xl bg-accent-50 text-accent-600"><x-icon name="badge" class="h-8 w-8" /></span>
                            @endif
                            <div class="min-w-0">
                                <h2 class="font-display text-lg font-semibold leading-snug text-slate-900">{{ $certification->name }}</h2>
                                @if ($certification->issuer)<p class="text-sm text-slate-600">{{ $certification->issuer }}</p>@endif
                            </div>
                        </div>
                        <dl class="mt-4 space-y-1 text-sm text-slate-500">
                            <div class="flex gap-2"><dt>Obtenue :</dt><dd class="font-medium text-slate-700">{{ $certification->formatDate($certification->issued_at) }}</dd></div>
                            @if ($certification->expires_at)
                                <div class="flex items-center gap-2">
                                    <dt>{{ $certification->isExpired() ? 'Expirée :' : 'Valable jusqu\'à :' }}</dt>
                                    <dd class="font-medium {{ $certification->isExpired() ? 'text-red-600' : 'text-slate-700' }}">{{ $certification->formatDate($certification->expires_at) }}</dd>
                                </div>
                            @endif
                            @if ($certification->credential_id)
                                <div class="flex gap-2"><dt>Identifiant :</dt><dd class="break-all font-mono text-xs text-slate-700">{{ $certification->credential_id }}</dd></div>
                            @endif
                        </dl>
                        @if ($certification->description)
                            <p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $certification->description }}</p>
                        @endif
                        @if ($certification->credential_url)
                            <a href="{{ $certification->credential_url }}" target="_blank" rel="noopener" class="mt-auto inline-flex items-center gap-1 pt-4 text-sm font-semibold text-primary-600 hover:text-primary-700">
                                Vérifier la certification <x-icon name="external" class="h-4 w-4" />
                            </a>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-public-layout>
