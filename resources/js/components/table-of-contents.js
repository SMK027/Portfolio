// Sommaire d'article : section en cours surlignée, branches repliables.
// parents : { id du titre: [ids de ses ancêtres, du plus haut au plus proche] }
export default function tableOfContents(parents) {
    const ids = Object.keys(parents);
    const branches = [...new Set(Object.values(parents).flat())]; // titres ayant des sous-titres

    return {
        active: ids[0] ?? null,
        collapsed: {},
        wide: false,

        isOpen(id) {
            return ! this.collapsed[id];
        },
        toggle(id) {
            this.collapsed = { ...this.collapsed, [id]: ! this.collapsed[id] };
        },
        get allCollapsed() {
            return branches.length > 0 && branches.every((id) => this.collapsed[id]);
        },
        toggleAll() {
            const collapse = ! this.allCollapsed;
            this.collapsed = Object.fromEntries(branches.map((id) => [id, collapse]));
        },
        // Élément surligné : la section lue, ou son ancêtre visible le plus haut si elle est repliée.
        get shown() {
            return (parents[this.active] ?? []).find((id) => this.collapsed[id]) ?? this.active;
        },
        go(id, link) {
            this.active = id;
            if (! this.wide) link.closest('details').open = false;
        },

        init() {
            const headings = ids.map((id) => document.getElementById(id)).filter(Boolean);
            if (! headings.length) return;

            let scheduled = false;
            const update = () => {
                scheduled = false;
                // En bas de page, la dernière section est lue même si son titre ne remonte pas.
                const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
                const passed = headings.filter((h) => h.getBoundingClientRect().top <= 120);
                this.active = (atBottom ? headings.at(-1) : passed.at(-1) ?? headings[0]).id;
            };
            window.addEventListener('scroll', () => {
                if (! scheduled) { scheduled = true; requestAnimationFrame(update); }
            }, { passive: true });
            update();

            window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => (this.wide = e.matches));
        },
    };
}
