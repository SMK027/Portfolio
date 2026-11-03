// Barre de progression de lecture d'un article : part du haut de l'article
// et atteint 100 % quand sa fin arrive en bas de l'écran.
export default function readingProgress() {
    return {
        progress: 0,

        init() {
            const target = document.querySelector(this.$el.dataset.target);
            if (! target) {
                return;
            }

            let frame = null;
            const update = () => {
                frame = null;
                const rect = target.getBoundingClientRect();
                const distance = rect.height - window.innerHeight;
                const ratio = distance <= 0 ? (rect.top <= 0 ? 1 : 0) : -rect.top / distance;
                this.progress = Math.round(Math.min(1, Math.max(0, ratio)) * 1000) / 10;
            };
            const schedule = () => { frame ??= requestAnimationFrame(update); };

            window.addEventListener('scroll', schedule, { passive: true });
            window.addEventListener('resize', schedule, { passive: true });
            update();
        },
    };
}
