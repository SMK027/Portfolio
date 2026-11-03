// Réactions emoji d'un article : bascule optimiste, puis compteurs renvoyés par le serveur.
export default function articleReactions(initial, url) {
    return {
        counts: { ...initial.counts },
        mine: [...initial.mine],
        busy: false,
        error: null,

        async toggle(emoji) {
            if (this.busy) {
                return;
            }
            const previous = { counts: { ...this.counts }, mine: [...this.mine] };
            const active = this.mine.includes(emoji);
            this.mine = active ? this.mine.filter((e) => e !== emoji) : [...this.mine, emoji];
            this.counts[emoji] = Math.max(0, (this.counts[emoji] ?? 0) + (active ? -1 : 1));
            this.busy = true;
            this.error = null;

            try {
                const { data } = await window.axios.post(url, { emoji });
                this.counts = data.counts;
                this.mine = data.mine;
            } catch (e) {
                this.counts = previous.counts;
                this.mine = previous.mine;
                this.error = e.response?.status === 429
                    ? 'Trop de réactions en peu de temps, réessayez dans une minute.'
                    : 'La réaction n\'a pas pu être enregistrée.';
            } finally {
                this.busy = false;
            }
        },
    };
}
