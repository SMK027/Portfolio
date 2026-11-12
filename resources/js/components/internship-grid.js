// Éditeur en masse de la recherche de stage : plein écran, lignes modifiées, filtres dynamiques.
const normalize = (text) => String(text ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

// Valeur filtrable d'une cellule : texte saisi, clé de la liste, date sous ses deux formes.
const cellValue = (cell) => {
    const field = cell.querySelector('input, select, textarea');
    if (! field) return { text: '', key: '' };
    if (field.tagName === 'SELECT') {
        return { text: normalize(field.selectedOptions[0]?.text), key: field.value };
    }
    if (field.type === 'date' && field.value) {
        const [y, m, d] = field.value.split('-');
        return { text: `${field.value} ${d}/${m}/${y} ${d}/${m}`, key: field.value };
    }
    return { text: normalize(field.value), key: field.value };
};

export default (hasErrors = false) => ({
    dirty: hasErrors,
    full: (() => { try { return localStorage.getItem('internship-grid.full') !== '0'; } catch { return true; } })(),
    native: false,
    filters: { all: '', dirtyOnly: false, columns: {} },
    total: 0,
    shown: 0,

    init() {
        document.addEventListener('fullscreenchange', () => { this.native = !! document.fullscreenElement; });
        this.total = this.shown = this.rows().length;
    },

    rows() {
        return [...this.$root.querySelectorAll('tr[data-row]')];
    },

    get active() {
        return this.filters.all.trim() !== '' || this.filters.dirtyOnly || Object.values(this.filters.columns).some((value) => String(value ?? '').trim() !== '');
    },

    // Filtre sur l'état actuel des cellules ; une ligne modifiée ensuite reste visible jusqu'au prochain filtrage.
    apply() {
        const words = normalize(this.filters.all).split(/\s+/).filter(Boolean);
        const columns = Object.entries(this.filters.columns)
            .map(([index, value]) => [Number(index), normalize(value)])
            .filter(([, value]) => value !== '');
        let shown = 0;

        this.rows().forEach((row) => {
            const cells = [...row.children].map(cellValue);
            const visible = (! this.filters.dirtyOnly || row.hasAttribute('data-dirty'))
                && words.every((word) => cells.some((cell) => cell.text.includes(word)))
                && columns.every(([index, value]) => {
                    const cell = cells[index];
                    if (! cell) return true;
                    if ([4, 5].includes(index)) return cell.key === value;           // listes : valeur exacte
                    if (['vide', '-', '—'].includes(value)) return cell.text === '';  // cellule vide
                    return cell.text.includes(value);
                });
            row.hidden = ! visible;
            shown += visible ? 1 : 0;
        });
        this.shown = shown;
    },

    reset() {
        this.filters = { all: '', dirtyOnly: false, columns: {} };
        this.apply();
    },

    markDirty(event) {
        const row = event.target.closest('tr[data-row]');
        if (! row) return;
        row.setAttribute('data-dirty', '');
        this.dirty = true;
    },

    toggle() {
        this.full = ! this.full;
        try { localStorage.setItem('internship-grid.full', this.full ? '1' : '0'); } catch {}
        if (! this.full && document.fullscreenElement) document.exitFullscreen();
    },

    browserFullscreen() {
        this.full = true;
        document.fullscreenElement ? document.exitFullscreen() : this.$root.requestFullscreen?.().catch(() => {});
    },
});
