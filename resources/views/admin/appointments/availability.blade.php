@php
    $oldRules = old('rules', $rules->map(fn ($r) => ['weekday' => $r->weekday, 'start_time' => substr($r->start_time, 0, 5), 'end_time' => substr($r->end_time, 0, 5)])->all());
    $oldClosures = old('closures', $closures->map(fn ($c) => ['date' => $c->date->toDateString(), 'reason' => $c->reason])->all());
@endphp
<x-app-layout>
    <x-slot name="title">Disponibilités</x-slot>
    <x-slot name="header">Disponibilités pour les rendez-vous</x-slot>
    <x-slot name="actions"><a href="{{ route('admin.appointments.index') }}" class="btn-secondary"><x-icon name="arrow-left" class="h-4 w-4" /> Rendez-vous</a></x-slot>

    <form method="POST" action="{{ route('admin.appointments.availability.update') }}" class="space-y-6"
          x-data="{ rules: @js(array_values($oldRules)), closures: @js(array_values($oldClosures)) }">
        @csrf @method('PUT')

        <x-admin.section title="Créneaux" description="Les visiteurs voient les créneaux libres découpés dans vos plages hebdomadaires.">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.select name="duration" label="Durée d'un rendez-vous" :value="$settings['duration']" :options="[15 => '15 min', 20 => '20 min', 30 => '30 min', 45 => '45 min', 60 => '1 h', 90 => '1 h 30']" required />
                <x-form.input name="notice_hours" type="number" min="0" max="336" label="Délai de prévenance (heures)" :value="$settings['notice_hours']" required help="Pas de réservation moins de X heures à l'avance." />
                <x-form.input name="horizon_days" type="number" min="1" max="180" label="Réservable jusqu'à (jours)" :value="$settings['horizon_days']" required />
            </div>
            <x-form.input name="location" label="Lieu ou modalité" :value="$settings['location']" required maxlength="255" help="Affiché aux visiteurs et dans l'invitation d'agenda." />
            <x-form.input name="topics" label="Sujets proposés" :value="implode(', ', $settings['topics'])" required maxlength="500" help="Séparés par des virgules." />
        </x-admin.section>

        <x-admin.section title="Plages hebdomadaires">
            <template x-for="(rule, i) in rules" :key="i">
                <div class="flex flex-wrap items-end gap-2">
                    <label class="flex-1"><span class="form-label">Jour</span>
                        <select :name="`rules[${i}][weekday]`" x-model="rule.weekday" class="form-select">
                            @foreach (\App\Models\AvailabilityRule::WEEKDAYS as $value => $day)<option value="{{ $value }}">{{ $day }}</option>@endforeach
                        </select>
                    </label>
                    <label><span class="form-label">De</span><input type="time" :name="`rules[${i}][start_time]`" x-model="rule.start_time" required class="form-input"></label>
                    <label><span class="form-label">À</span><input type="time" :name="`rules[${i}][end_time]`" x-model="rule.end_time" required class="form-input"></label>
                    <button type="button" @click="rules.splice(i, 1)" class="btn-ghost text-red-600" aria-label="Retirer la plage"><x-icon name="trash" class="h-4 w-4" /></button>
                </div>
            </template>
            <p x-show="! rules.length" class="text-sm text-amber-700">Aucune plage : aucun créneau n'est proposé.</p>
            <button type="button" @click="rules.push({ weekday: 2, start_time: '14:00', end_time: '17:00' })" class="btn-secondary btn-sm"><x-icon name="plus" class="h-4 w-4" /> Ajouter une plage</button>
            @foreach ($errors->get('rules.*') as $messages)@foreach ($messages as $m)<p class="form-error">{{ $m }}</p>@endforeach @endforeach
        </x-admin.section>

        <x-admin.section title="Jours fermés" description="Congés, examens… aucun créneau ces jours-là.">
            <template x-for="(closure, i) in closures" :key="i">
                <div class="flex flex-wrap items-end gap-2">
                    <label><span class="form-label">Date</span><input type="date" :name="`closures[${i}][date]`" x-model="closure.date" required min="{{ today()->toDateString() }}" class="form-input"></label>
                    <label class="flex-1"><span class="form-label">Motif (privé)</span><input type="text" :name="`closures[${i}][reason]`" x-model="closure.reason" maxlength="150" class="form-input"></label>
                    <button type="button" @click="closures.splice(i, 1)" class="btn-ghost text-red-600" aria-label="Retirer le jour"><x-icon name="trash" class="h-4 w-4" /></button>
                </div>
            </template>
            <button type="button" @click="closures.push({ date: '', reason: '' })" class="btn-secondary btn-sm"><x-icon name="plus" class="h-4 w-4" /> Ajouter un jour</button>
            @foreach ($errors->get('closures.*') as $messages)@foreach ($messages as $m)<p class="form-error">{{ $m }}</p>@endforeach @endforeach
        </x-admin.section>

        <x-admin.form-actions can="appointments.write" :cancel="route('admin.appointments.index')" />
    </form>
</x-app-layout>
