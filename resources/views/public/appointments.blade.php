@php
    $first = $days->keys()->first();
    $selected = old('slot') ? substr(old('slot'), 0, 10) : $first;
@endphp
<x-public-layout :page="$page" :title="$page->title">
    <x-page-header :page="$page" />

    <div class="mx-auto max-w-5xl px-4 py-12 sm:px-6">
        @if (session('success'))
            <div class="mb-8 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('success') }}</div>
        @endif

        @if ($days->isEmpty())
            <x-empty-state icon="calendar" message="Aucun créneau disponible pour le moment. Revenez bientôt ou utilisez le formulaire de contact." />
        @else
            <form method="POST" action="{{ route('appointments.store') }}" x-data="{ ...contactForm({ siteKey: @js($recaptchaSiteKey), action: 'appointment' }), day: @js($selected), slot: @js(old('slot')) }"
                  @submit.prevent="submit($event)" class="grid gap-8 lg:grid-cols-[1fr_22rem]">
                @csrf
                <input type="hidden" name="recaptcha_token" value="">
                <input type="hidden" name="slot" :value="slot">
                <div class="hidden" aria-hidden="true"><label for="website">Ne pas remplir</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>

                {{-- 1. Jour puis heure --}}
                <section class="space-y-5">
                    <div>
                        <h2 class="font-display text-lg font-semibold text-slate-900">1. Choisissez un jour</h2>
                        <p class="text-sm text-slate-500">Créneaux de {{ $settings['duration'] }} minutes · {{ $settings['location'] }}</p>
                    </div>
                    <div class="flex gap-2 overflow-x-auto pb-2" role="listbox" aria-label="Jours disponibles">
                        @foreach ($days as $date => $slots)
                            @php $d = \Carbon\CarbonImmutable::parse($date); @endphp
                            <button type="button" role="option" @click="day = @js($date); slot = null" :aria-selected="(day === @js($date)).toString()"
                                    :class="day === @js($date) ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-slate-200 bg-white text-slate-700 hover:border-primary-300'"
                                    class="flex w-20 flex-none flex-col items-center rounded-xl border px-2 py-3 transition">
                                <span class="text-xs uppercase">{{ $d->translatedFormat('D') }}</span>
                                <span class="font-display text-2xl font-bold">{{ $d->format('j') }}</span>
                                <span class="text-xs">{{ $d->translatedFormat('M') }}</span>
                                <span class="mt-1 text-[11px] text-slate-500">{{ $slots->count() }} créneau{{ $slots->count() > 1 ? 'x' : '' }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div>
                        <h2 class="mb-3 font-display text-lg font-semibold text-slate-900">2. Choisissez l'heure</h2>
                        @foreach ($days as $date => $slots)
                            <div x-show="day === @js($date)" @if ($date !== $selected) x-cloak @endif class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5">
                                @foreach ($slots as $start)
                                    @php $value = $start->format('Y-m-d H:i'); @endphp
                                    <button type="button" @click="slot = @js($value)" :aria-pressed="(slot === @js($value)).toString()"
                                            :class="slot === @js($value) ? 'border-primary-600 bg-primary-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-primary-300'"
                                            class="rounded-lg border px-3 py-2 text-sm font-medium transition">{{ $start->format('H:i') }}</button>
                                @endforeach
                            </div>
                        @endforeach
                        @error('slot')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </section>

                {{-- 3. Coordonnées --}}
                <section class="card space-y-4 p-5 sm:p-6">
                    <h2 class="font-display text-lg font-semibold text-slate-900">3. Vos coordonnées</h2>
                    <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm" :class="slot ? 'text-slate-800' : 'text-slate-400'"
                       x-text="slot ? 'Créneau : ' + new Date(slot.replace(' ', 'T')).toLocaleString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }) : 'Aucun créneau choisi'"></p>
                    <x-form.input name="name" label="Nom et prénom" required autocomplete="name" maxlength="150" />
                    <x-form.input name="email" type="email" label="Adresse e-mail" required autocomplete="email" maxlength="255" />
                    <x-form.input name="phone" type="tel" label="Téléphone (facultatif)" autocomplete="tel" maxlength="40" />
                    <x-form.select name="topic" label="Sujet" required :options="array_combine($settings['topics'], $settings['topics'])" />
                    <x-form.textarea name="message" label="Message (facultatif)" rows="3" maxlength="2000" />
                    <label class="flex items-start gap-3 text-sm text-slate-600">
                        <input type="checkbox" name="consent" value="1" @checked(old('consent')) required class="mt-0.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        <span>J'accepte que ces informations soient utilisées pour organiser ce rendez-vous. <span class="text-red-500">*</span></span>
                    </label>
                    @error('consent')<p class="form-error">{{ $message }}</p>@enderror
                    @error('recaptcha')<p class="form-error">{{ $message }}</p>@enderror
                    <p x-show="captchaError" x-cloak class="form-error">Impossible de joindre le service anti-robot. Réessayez.</p>
                    <button class="btn-primary w-full" :disabled="sending || ! slot"><x-icon name="calendar" class="h-4 w-4" /> <span x-text="sending ? 'Envoi…' : 'Demander ce rendez-vous'"></span></button>
                    <p class="text-xs text-slate-400">La demande est confirmée par e-mail. Vous pourrez l'annuler à tout moment depuis cet e-mail.</p>
                </section>
            </form>
        @endif
    </div>

    @if ($recaptchaSiteKey && $days->isNotEmpty())
        @push('scripts')
            <script src="https://www.google.com/recaptcha/api.js?render={{ urlencode($recaptchaSiteKey) }}" async defer></script>
        @endpush
    @endif
</x-public-layout>
