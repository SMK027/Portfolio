// Plage de disponibilité cliquée dans le calendrier : duplication ou suppression.
export default () => ({
    range: null,
    weekdays: [],
    dates: [],
    newDate: '',
    busy: false,
    error: null,

    open(detail) {
        Object.assign(this, { range: detail, weekdays: [], dates: [], newDate: '', error: null, busy: false });
    },

    addDate() {
        if (this.newDate && this.newDate !== this.range.date && ! this.dates.includes(this.newDate)) {
            this.dates = [...this.dates, this.newDate].sort();
        }
        this.newDate = '';
    },

    notify(message, type = 'success') {
        window.dispatchEvent(new CustomEvent('availability-notice', { detail: { message, type } }));
    },

    async send(request, success) {
        this.busy = true;
        this.error = null;
        try {
            const { data } = await request();
            window.dispatchEvent(new CustomEvent('availability-refresh'));
            this.notify(success(data));
            this.range = null;
        } catch (e) {
            const errors = e?.response?.data?.errors;
            this.error = (errors && Object.values(errors)[0]?.[0]) ?? e?.response?.data?.message ?? 'Opération impossible.';
        } finally {
            this.busy = false;
        }
    },

    duplicate() {
        const payload = this.range.kind === 'weekly' ? { weekdays: this.weekdays } : { dates: this.dates };
        this.send(() => window.axios.post(this.range.duplicateUrl, payload), (data) => `${data.count} copie(s) ajoutée(s).`);
    },

    remove() {
        const what = this.range.kind === 'weekly'
            ? (this.range.from ? 'cette plage sur toute sa période' : 'cette plage hebdomadaire (toutes les semaines)')
            : 'cette disponibilité ponctuelle';
        if (! window.confirm(`Supprimer ${what} ?`)) return;
        this.send(() => window.axios.delete(this.range.url), () => 'Plage supprimée.');
    },
});
