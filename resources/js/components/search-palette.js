// Palette de recherche du site public (Ctrl+K, ⌘K ou « / »).
// Raccourcis (ex. page des succès) : proposés quand la recherche est vide, ou s'ils correspondent.
const normalize = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

export default function searchPalette(url, shortcuts = []) {
    return {
        url, shortcuts, open: false, query: '', results: [...shortcuts], active: 0, loading: false, searched: false, controller: null,

        show() {
            this.open = true;
            this.$dispatch('achievement-event', 'palette_opened');
            this.$nextTick(() => this.$refs.input.focus());
        },
        shortcut(event) {
            const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || event.target.isContentEditable;
            if ((event.key === 'k' && (event.ctrlKey || event.metaKey)) || (event.key === '/' && ! typing)) {
                event.preventDefault();
                this.show();
            }
        },
        async fetchResults() {
            const q = this.query.trim();
            if (q.length < 2) { this.results = [...this.shortcuts]; this.active = 0; this.searched = false; return; }
            const matching = this.shortcuts.filter((s) => normalize(s.title + ' ' + s.excerpt).includes(normalize(q)));
            this.controller?.abort();
            this.controller = new AbortController();
            this.loading = true;
            try {
                const response = await fetch(`${this.url}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' }, signal: this.controller.signal });
                this.results = [...matching, ...(await response.json()).results];
                this.active = 0;
                this.searched = true;
            } catch (e) {
                if (e.name !== 'AbortError') this.results = matching;
            } finally {
                this.loading = false;
            }
        },
        move(step) {
            if (this.results.length) this.active = (this.active + step + this.results.length) % this.results.length;
        },
        choose(event) {
            // Entrée sur une suggestion : on l'ouvre ; sinon, page de résultats complète.
            if (this.results[this.active]) {
                event.preventDefault();
                window.location.assign(this.results[this.active].url);
            }
        },
    };
}
