<x-app-layout>
    <x-slot name="title">Journal d'activité</x-slot>
    <x-slot name="header">Journal d'activité</x-slot>

    <p class="text-sm text-slate-500">
        Toutes les opérations d'administration (panel, API des comptes de service, console) : connexions, créations, modifications,
        suppressions, validations, imports… Les actions des visiteurs sur le site public ne sont pas enregistrées. Le journal ne peut être ni modifié ni effacé depuis le site.
    </p>

    <form method="GET" class="card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher (élément, auteur, IP…)" class="form-input lg:col-span-2">
        <select name="category" class="form-input">
            <option value="">Toutes les catégories</option>
            @foreach ($categories as $key => $label)<option value="{{ $key }}" @selected(($filters['category'] ?? '') === $key)>{{ $label }}</option>@endforeach
        </select>
        <select name="user" class="form-input">
            <option value="">Tous les auteurs</option>
            @foreach ($users as $u)<option value="{{ $u->id }}" @selected((int) ($filters['user'] ?? 0) === $u->id)>{{ $u->name }}</option>@endforeach
        </select>
        <select name="via" class="form-input">
            <option value="">Panel, API et console</option>
            @foreach (\App\Models\AuditLog::VIAS as $key => $label)<option value="{{ $key }}" @selected(($filters['via'] ?? '') === $key)>{{ $label }}</option>@endforeach
        </select>
        <div class="flex gap-2 lg:col-span-6">
            <label class="flex items-center gap-2 text-sm text-slate-600">Du <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-input"></label>
            <label class="flex items-center gap-2 text-sm text-slate-600">au <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-input"></label>
            <button class="btn-primary ml-auto">Filtrer</button>
            @if (array_filter($filters))<a href="{{ route('admin.audit.index') }}" class="btn-secondary">Réinitialiser</a>@endif
        </div>
    </form>

    @if ($logs->isEmpty())
        <x-empty-state icon="clock" message="Aucune opération ne correspond." />
    @else
        <div class="card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Date</th><th>Auteur</th><th>Action</th><th class="hidden md:table-cell">Élément</th><th class="hidden lg:table-cell">Origine</th><th class="w-12"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap text-xs text-slate-500">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="text-sm">
                                {{ $log->actor_name ?? '—' }}
                                @if ($log->actor_role === 'service')<span class="badge-slate ml-1">service</span>@endif
                            </td>
                            <td>
                                <span @class([
                                    'badge',
                                    'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200' => str_ends_with($log->action, '.deleted') || in_array($log->action, ['auth.failed', 'auth.lockout', 'api.auth_failed'], true),
                                    'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' => str_ends_with($log->action, '.created'),
                                    'badge-slate' => ! str_ends_with($log->action, '.deleted') && ! str_ends_with($log->action, '.created') && ! in_array($log->action, ['auth.failed', 'auth.lockout', 'api.auth_failed'], true),
                                ])>{{ $log->actionLabel() }}</span>
                            </td>
                            <td class="hidden text-sm md:table-cell">
                                @if ($log->subject_type)<span class="text-xs text-slate-400">{{ $log->subjectLabel() }}</span><br>@endif
                                <span class="text-slate-700">{{ \Illuminate\Support\Str::limit($log->subject_label, 60) }}</span>
                            </td>
                            <td class="hidden text-xs text-slate-500 lg:table-cell">{{ $log->viaLabel() }}@if ($log->ip_address) · {{ $log->ip_address }}@endif</td>
                            <td class="text-right"><a href="{{ route('admin.audit.show', $log) }}" class="inline-block rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Détail" aria-label="Détail"><x-icon name="eye" class="h-4 w-4" /></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    @endif
</x-app-layout>
