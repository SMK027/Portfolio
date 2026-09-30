@php
    $display = fn ($value) => match (true) {
        $value === null => '—',
        is_bool($value) => $value ? 'oui' : 'non',
        is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        default => (string) $value,
    };
@endphp
<x-app-layout>
    <x-slot name="title">Opération #{{ $log->id }}</x-slot>
    <x-slot name="header">{{ $log->actionLabel() }}</x-slot>
    <x-slot name="actions"><a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('admin.audit.index') }}" class="btn-secondary"><x-icon name="arrow-left" class="h-4 w-4" /> Retour</a></x-slot>

    <x-admin.section>
        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">Date</dt><dd class="font-medium text-slate-900">{{ $log->created_at->translatedFormat('j F Y à H:i:s') }}</dd></div>
            <div><dt class="text-slate-500">Auteur</dt><dd class="font-medium text-slate-900">{{ $log->actor_name ?? '—' }} @if ($log->actor_role)<span class="text-slate-500">({{ \App\Models\User::ROLES[$log->actor_role] ?? $log->actor_role }})</span>@endif</dd></div>
            <div><dt class="text-slate-500">Action</dt><dd><code class="text-xs">{{ $log->action }}</code></dd></div>
            <div><dt class="text-slate-500">Origine</dt><dd>{{ $log->viaLabel() }}@if ($log->ip_address) — {{ $log->ip_address }}@endif</dd></div>
            @if ($log->subject_type)
                <div class="sm:col-span-2"><dt class="text-slate-500">Élément</dt><dd>{{ $log->subjectLabel() }} « {{ $log->subject_label }} »@if ($log->subject_id) <span class="text-slate-400">#{{ $log->subject_id }}</span>@endif</dd></div>
            @endif
            @if ($log->user_agent)
                <div class="sm:col-span-2"><dt class="text-slate-500">Navigateur / client</dt><dd class="break-all text-xs text-slate-600">{{ $log->user_agent }}</dd></div>
            @endif
        </dl>
    </x-admin.section>

    @if ($log->changes)
        <x-admin.section title="Champs">
            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead><tr><th>Champ</th><th>Avant</th><th>Après</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($log->changes as $field => $change)
                            <tr>
                                <td class="align-top font-mono text-xs text-slate-600">{{ $field }}</td>
                                <td class="align-top"><pre class="whitespace-pre-wrap break-words text-xs text-red-700">{{ array_key_exists('old', (array) $change) ? $display($change['old']) : '' }}</pre></td>
                                <td class="align-top"><pre class="whitespace-pre-wrap break-words text-xs text-emerald-700">{{ $display($change['new'] ?? null) }}</pre></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.section>
    @endif

    @if ($log->meta)
        <x-admin.section title="Informations complémentaires">
            <pre class="overflow-x-auto whitespace-pre-wrap break-words rounded-lg bg-slate-50 p-4 text-xs text-slate-700">{{ json_encode($log->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}</pre>
        </x-admin.section>
    @endif
</x-app-layout>
