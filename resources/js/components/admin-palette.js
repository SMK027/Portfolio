// Palette du panel d'administration (Ctrl+K, ⌘K) : pages du menu latéral, filtrées au fil de la frappe.
const normalize = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

export default () => ({
    open: false,
    query: '',
    active: 0,
    commands: [],

    init() {
        try {
            this.commands = JSON.parse(document.getElementById('admin-commands')?.textContent ?? '[]');
        } catch {
            this.commands = [];
        }
        this.commands.forEach((command) => { command.search = normalize(`${command.label} ${command.group}`); });
    },

    get results() {
        const words = normalize(this.query).split(/\s+/).filter(Boolean);
        if (! words.length) return this.commands;
        // Tous les mots doivent figurer ; les libellés commençant par la recherche passent en tête.
        return this.commands
            .filter((command) => words.every((word) => command.search.includes(word)))
            .sort((a, b) => Number(! normalize(a.label).startsWith(words[0])) - Number(! normalize(b.label).startsWith(words[0])));
    },

    show() {
        this.open = true;
        this.query = '';
        this.active = 0;
        this.$nextTick(() => this.$refs.input.focus());
    },

    shortcut(event) {
        if (event.key.toLowerCase() === 'k' && (event.ctrlKey || event.metaKey) && ! event.altKey) {
            event.preventDefault();
            this.open ? (this.open = false) : this.show();
        }
    },

    move(step) {
        const count = this.results.length;
        if (! count) return;
        this.active = (this.active + step + count) % count;
        this.$nextTick(() => document.getElementById(`admin-palette-${this.active}`)?.scrollIntoView({ block: 'nearest' }));
    },

    go(event) {
        const command = this.results[this.active];
        if (! command) return;
        this.open = false;
        if (command.external || event.ctrlKey || event.metaKey) {
            window.open(command.url, '_blank', 'noopener');
        } else {
            window.location.assign(command.url);
        }
    },
});
