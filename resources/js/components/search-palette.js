// Palette de recherche du site public (Ctrl+K, ⌘K ou « / »).
export default function searchPalette(url) {
    return {
        url, open: false, query: '', results: [], active: 0, loading: false, searched: false, controller: null,

        show() {
            this.open = true;
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
            if (q.length < 2) { this.results = []; this.searched = false; return; }
            this.controller?.abort();
            this.controller = new AbortController();
            this.loading = true;
            try {
                const response = await fetch(`${this.url}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' }, signal: this.controller.signal });
                this.results = (await response.json()).results;
                this.active = 0;
                this.searched = true;
            } catch (e) {
                if (e.name !== 'AbortError') this.results = [];
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
