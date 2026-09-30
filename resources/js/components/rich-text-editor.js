/**
 * Bascule entre l'éditeur visuel (Editor.js) et le Markdown.
 * Les conversions sont faites par le serveur (mêmes règles qu'à l'enregistrement),
 * et l'aperçu Markdown utilise le même rendu que le site public.
 */
export default ({ mode = 'blocks', toMarkdownUrl, toBlocksUrl }) => ({
    mode,
    markdown: '',
    previewHtml: '',
    loading: false,
    error: '',
    previewTimer: null,

    init() {
        this.markdown = this.$refs.markdown.value;
        if (this.mode === 'markdown') this.refreshPreview();

        // Image insérée depuis la galerie : en Markdown, on ajoute la syntaxe d'image.
        window.addEventListener('editor-insert-image', (event) => {
            const form = this.$root.closest('form');
            if (this.mode !== 'markdown' || !form || !form.contains(event.target)) return;
            const { url, caption = '' } = event.detail || {};
            this.insertAtCursor(`\n\n![${caption}](${url})\n\n`);
        });
    },

    /** Instance Editor.js (créée par resources/js/editor.js). */
    async editor() {
        for (let i = 0; i < 50 && !this.$refs.holder.editorInstance; i++) {
            await new Promise((resolve) => setTimeout(resolve, 100));
        }
        const editor = this.$refs.holder.editorInstance;
        await editor.isReady;
        return editor;
    },

    async switchTo(target) {
        if (target === this.mode || this.loading) return;
        this.loading = true;
        this.error = '';

        try {
            const editor = await this.editor();
            if (target === 'markdown') {
                const content = await editor.save();
                const { data } = await window.axios.post(toMarkdownUrl, { content });
                this.markdown = data.markdown.trim() === '' ? '' : data.markdown;
                this.mode = 'markdown';
                await this.refreshPreview();
            } else {
                const { data } = await window.axios.post(toBlocksUrl, { markdown: this.markdown });
                if (data.content.blocks.length) {
                    await editor.render(data.content);
                } else {
                    await editor.clear();
                }
                this.mode = 'blocks';
            }
        } catch (e) {
            this.error = 'La conversion a échoué. Réessayez ; votre contenu n\'a pas été modifié.';
        } finally {
            this.loading = false;
        }
    },

    schedulePreview() {
        clearTimeout(this.previewTimer);
        this.previewTimer = setTimeout(() => this.refreshPreview(), 400);
    },

    async refreshPreview() {
        try {
            const { data } = await window.axios.post(toBlocksUrl, { markdown: this.markdown });
            this.previewHtml = data.html || '<p class="text-slate-400">Aperçu du contenu…</p>';
        } catch (e) {
            // L'aperçu n'est pas bloquant.
        }
    },

    /** Entoure la sélection (ou insère au curseur) une syntaxe Markdown. */
    wrap(before, after) {
        const area = this.$refs.markdown;
        const { selectionStart: start, selectionEnd: end, value } = area;
        const selected = value.slice(start, end);
        this.markdown = value.slice(0, start) + before + selected + after + value.slice(end);
        this.$nextTick(() => {
            area.focus();
            area.setSelectionRange(start + before.length, start + before.length + selected.length);
            this.schedulePreview();
        });
    },

    insertAtCursor(text) {
        const area = this.$refs.markdown;
        const position = area.selectionStart ?? this.markdown.length;
        this.markdown = this.markdown.slice(0, position) + text + this.markdown.slice(position);
        this.$nextTick(() => {
            area.focus();
            area.setSelectionRange(position + text.length, position + text.length);
            this.schedulePreview();
        });
    },
});
