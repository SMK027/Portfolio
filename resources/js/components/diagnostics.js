// Page de diagnostic (super-administrateurs) : état interrogé à intervalle régulier.
export default (url, initial) => ({
    state: initial,
    interval: 5,
    paused: false,
    loading: false,
    failed: false,
    updatedAt: Date.now(),
    now: Date.now(),

    init() {
        // Horloge d'affichage + rafraîchissement quand l'intervalle est écoulé (onglet visible seulement).
        setInterval(() => {
            this.now = Date.now();
            if (! this.paused && ! this.loading && ! document.hidden && this.now - this.updatedAt >= this.interval * 1000) {
                this.refresh();
            }
        }, 1000);
    },

    get ago() {
        const seconds = Math.max(0, Math.round((this.now - this.updatedAt) / 1000));
        return seconds < 2 ? 'à l\'instant' : `il y a ${seconds} s`;
    },

    async refresh() {
        this.loading = true;
        try {
            const { data } = await window.axios.get(url);
            this.state = data;
            this.failed = false;
        } catch {
            this.failed = true;
        } finally {
            this.updatedAt = Date.now();
            this.loading = false;
        }
    },

    dot(status) {
        return { ok: 'bg-emerald-500', warning: 'bg-amber-400', error: 'bg-red-500' }[status] ?? 'bg-slate-300';
    },
});
