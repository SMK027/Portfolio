// Horaires bloqués (panel → Rendez-vous → Disponibilités) : création avec confirmation
// quand des rendez-vous sont déjà prévus, consultation et suppression.
export default ({ storeUrl }) => ({
    form: null,   // blocage en cours de création
    info: null,   // blocage cliqué dans le calendrier
    busy: false,

    // Sélection faite sur la grille, choix « Bloquer ce créneau ».
    open(detail) {
        this.form = { ...detail, type: 'appointment', channel: 'email', note: '', message: '', conflicts: null, error: null };
    },

    async save(confirm = false) {
        this.busy = true;
        this.form.error = null;
        const { start, end, type, channel, note, message } = this.form;
        try {
            const { data } = await window.axios.post(storeUrl, { start, end, type, channel: type === 'appointment' ? channel : null, note, message, confirm });
            this.form = null;
            this.done(data.message, data.failed?.length ? 'error' : 'success');
        } catch (error) {
            const response = error?.response;
            if (response?.status === 409) {
                // Rendez-vous sur l'horaire : confirmation demandée avant d'annuler et de prévenir.
                this.form.conflicts = response.data.conflicts;
            } else {
                this.form.error = response?.data?.message ?? 'Enregistrement impossible.';
            }
        } finally {
            this.busy = false;
        }
    },

    async remove() {
        this.busy = true;
        try {
            await window.axios.delete(this.info.deleteUrl);
            this.info = null;
            this.done('Blocage supprimé : l\'horaire est de nouveau proposé.', 'success');
        } catch (error) {
            this.done(error?.response?.data?.message ?? 'Suppression impossible.', 'error');
        } finally {
            this.busy = false;
        }
    },

    done(message, type) {
        window.dispatchEvent(new CustomEvent('availability-refresh'));
        window.dispatchEvent(new CustomEvent('availability-notice', { detail: { message, type } }));
    },
});
