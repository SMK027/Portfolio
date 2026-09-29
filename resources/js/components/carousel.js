/**
 * Carrousel d'images (Alpine) avec navigation clavier, balayage tactile
 * et affichage plein écran.
 *
 * Usage : x-data="carousel({ count: 4 })"
 */
export default ({ count = 0, autoplay = 0 } = {}) => ({
    index: 0,
    count,
    fullscreen: false,
    touchStartX: null,
    timer: null,

    init() {
        if (autoplay && this.count > 1) {
            this.timer = setInterval(() => {
                if (!this.fullscreen) this.next();
            }, autoplay);
        }
    },

    destroy() {
        clearInterval(this.timer);
    },

    stopAutoplay() {
        clearInterval(this.timer);
        this.timer = null;
    },

    next() {
        this.index = (this.index + 1) % this.count;
    },

    prev() {
        this.index = (this.index - 1 + this.count) % this.count;
    },

    go(i) {
        this.stopAutoplay();
        this.index = i;
    },

    open(i = this.index) {
        this.stopAutoplay();
        this.index = i;
        this.fullscreen = true;
        document.body.classList.add('overflow-hidden');
    },

    close() {
        this.fullscreen = false;
        document.body.classList.remove('overflow-hidden');
    },

    onTouchStart(event) {
        this.touchStartX = event.changedTouches[0].clientX;
    },

    onTouchEnd(event) {
        if (this.touchStartX === null) return;
        const delta = event.changedTouches[0].clientX - this.touchStartX;
        this.touchStartX = null;
        if (Math.abs(delta) < 40) return;
        this.stopAutoplay();
        delta < 0 ? this.next() : this.prev();
    },
});
