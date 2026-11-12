{{-- Export PDF / Excel : choix des colonnes du tableau des contacts (mémorisé dans le navigateur). --}}
@php $exportFields = collect(\App\Services\InternshipSearchExport::FIELDS)->map(fn ($meta) => $meta[0]); @endphp
<div class="relative" x-data="{
        open: false,
        all: @js($exportFields->keys()),
        fields: [],
        title: '',
        init() {
            try { this.fields = JSON.parse(localStorage.getItem('internship-export-fields')) ?? [...this.all] } catch { this.fields = [...this.all] }
            this.fields = this.fields.filter(field => this.all.includes(field));
            if (! this.fields.length) this.fields = [...this.all];
            this.$watch('fields', value => { try { localStorage.setItem('internship-export-fields', JSON.stringify(value)) } catch {} });
            try { this.title = localStorage.getItem('internship-export-title') ?? '' } catch {}
            this.$watch('title', value => { try { localStorage.setItem('internship-export-title', value) } catch {} });
        },
     }" @keydown.escape.window="open = false" @click.outside="open = false">
    <button type="button" class="btn-secondary" @click="open = ! open" :aria-expanded="open" aria-haspopup="true">
        <x-icon name="download" class="h-4 w-4" /> Exporter
    </button>

    <form method="GET" action="{{ route('admin.internship.export', 'pdf') }}" x-show="open" x-cloak x-transition.origin.top.right
          @submit="setTimeout(() => open = false)"
          class="card absolute right-0 z-30 mt-2 w-80 max-w-[calc(100vw-2rem)] space-y-3 p-4 text-sm shadow-lg">
        <div>
            <label for="export-title" class="font-semibold text-slate-800">Intitulé du document</label>
            <input id="export-title" type="text" name="titre" x-model="title" maxlength="120" class="form-input mt-1"
                   placeholder="{{ \App\Services\InternshipSearchExport::DEFAULT_TITLE }}">
            <p class="mt-1 text-xs text-slate-500">Ex. : Suivi de la recherche d'alternance. Repris dans le titre, le pied de page et le nom du fichier.</p>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-semibold text-slate-800">Colonnes à exporter</p>
            <button type="button" class="text-xs font-medium text-primary-600 hover:underline"
                    @click="fields = fields.length === all.length ? [] : [...all]"
                    x-text="fields.length === all.length ? 'Aucune' : 'Toutes'"></button>
        </div>
        <div class="space-y-1.5">
            @foreach ($exportFields as $key => $label)
                <label class="flex items-center gap-2 text-slate-700">
                    <input type="checkbox" name="champs[]" value="{{ $key }}" x-model="fields" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                    {{ $label }}
                </label>
            @endforeach
        </div>
        <p class="text-xs text-slate-500">La synthèse et les remarques sont toujours incluses.</p>
        <p class="text-xs text-red-600" x-show="! fields.length">Sélectionnez au moins une colonne.</p>
        <div class="flex justify-end gap-2">
            <button type="submit" class="btn-secondary btn-sm" :disabled="! fields.length"><x-icon name="download" class="h-4 w-4" /> PDF</button>
            <button type="submit" formaction="{{ route('admin.internship.export', 'excel') }}" class="btn-primary btn-sm" :disabled="! fields.length"><x-icon name="table" class="h-4 w-4" /> Excel</button>
        </div>
    </form>
</div>
