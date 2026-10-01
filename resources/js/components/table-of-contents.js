// Sommaire d'article : surligne la section en cours de lecture.
export default function tableOfContents(ids) {
    return {
        active: ids[0] ?? null,
        wide: false,
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
