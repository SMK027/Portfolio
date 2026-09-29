/**
 * Liste dynamique de liens (URL / dépôts GitHub) dans le formulaire projet.
 */
export default (initial = []) => ({
    links: initial.length ? initial : [],

    add() {
        this.links.push({ label: '', url: '' });
    },

    remove(index) {
        this.links.splice(index, 1);
    },
});
