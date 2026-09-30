import EditorJS from '@editorjs/editorjs';
import Header from '@editorjs/header';
import EditorList from '@editorjs/list';
import ImageTool from '@editorjs/image';
import Quote from '@editorjs/quote';
import Code from '@editorjs/code';
import Delimiter from '@editorjs/delimiter';
import Table from '@editorjs/table';
import Warning from '@editorjs/warning';
import Embed from '@editorjs/embed';
import InlineCode from '@editorjs/inline-code';
import Marker from '@editorjs/marker';
import Underline from '@editorjs/underline';
import AlignmentTune from 'editorjs-text-alignment-blocktune';
import ColorPlugin from 'editorjs-text-color-plugin';

/** Balise de transport d'un <iframe> collé (voir preparePastedHtml). */
const EMBED_TAG = 'pf-embed';

/**
 * Outil « embed » étendu : reçoit les <iframe> collées (YouTube, Vimeo, CodePen),
 * préalablement converties en <pf-embed data-src="…"> par preparePastedHtml().
 */
class EmbedWithIframes extends Embed {
    static get pasteConfig() {
        return { ...super.pasteConfig, tags: [{ [EMBED_TAG]: { 'data-src': true } }] };
    }

    static serviceFor(src) {
        const services = Embed.services || {};
        return Object.keys(services).find((name) => {
            services[name].regex.lastIndex = 0;
            return services[name].regex.test(src);
        });
    }

    onPaste(event) {
        if (event.type !== 'tag') {
            super.onPaste(event);
            return;
        }

        const src = event.detail.data.getAttribute('data-src') || '';
        const key = EmbedWithIframes.serviceFor(src);
        if (key) {
            Embed.services[key].regex.lastIndex = 0;
            super.onPaste({ detail: { key, data: src } });
        }
    }
}

/**
 * Outil « liste » corrigé au collage : l'outil d'origine recopiait la sous-liste
 * dans le texte de l'élément parent (texte dupliqué) et ignorait les sous-listes
 * d'un autre type (liste numérotée dans une liste à puces).
 */
class ListWithNestedPaste extends EditorList {
    /** Analyse une liste collée en conservant chaque niveau (quel que soit son type). */
    static parseList(list) {
        const parseItem = (li) => {
            const clone = li.cloneNode(true);
            clone.querySelectorAll(':scope > ul, :scope > ol').forEach((nested) => nested.remove());
            const nested = li.querySelector(':scope > ul, :scope > ol');
            return { content: clone.innerHTML.trim(), meta: {}, items: nested ? ListWithNestedPaste.parseList(nested) : [] };
        };

        return list.tagName === 'LI'
            ? [parseItem(list)]
            : Array.from(list.querySelectorAll(':scope > li')).map(parseItem);
    }

    onPaste(event) {
        // Même choix de style que l'outil d'origine (qui peut recréer this.list)…
        this.listStyle = event.detail.data.tagName === 'OL' ? 'ordered' : 'unordered';

        // …puis analyse corrigée sur l'instance interne qui traite réellement le collage.
        const list = this.list;
        const original = list.pasteHandler.bind(list);
        list.pasteHandler = (element) => ({ ...original(element), items: ListWithNestedPaste.parseList(element) });
        list.onPaste(event);
    }
}

/**
 * Prépare le HTML collé avant Editor.js, qui supprime tout élément sans texte :
 * les <iframe> de services reconnus deviennent des blocs vidéo, les autres un lien.
 */
function preparePastedHtml(html) {
    if (!/<iframe/i.test(html)) return null;

    const doc = new DOMParser().parseFromString(html, 'text/html');
    doc.querySelectorAll('iframe').forEach((iframe) => {
        const src = iframe.getAttribute('src') || '';
        if (!/^https?:\/\//i.test(src)) {
            iframe.remove();
            return;
        }
        let replacement;
        if (EmbedWithIframes.serviceFor(src)) {
            replacement = doc.createElement(EMBED_TAG);
            replacement.setAttribute('data-src', src);
            replacement.textContent = src;
        } else {
            replacement = doc.createElement('p');
            const link = doc.createElement('a');
            link.href = src;
            link.textContent = iframe.getAttribute('title') || src;
            replacement.appendChild(link);
        }
        iframe.replaceWith(replacement);
    });

    return doc.body.innerHTML;
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const PALETTE = [
    '#0f172a', '#475569', '#dc2626', '#ea580c', '#ca8a04',
    '#16a34a', '#0d9488', '#2563eb', '#4f46e5', '#9333ea', '#db2777',
];

const i18n = {
    messages: {
        ui: {
            blockTunes: { toggler: { 'Click to tune': 'Options', 'or drag to move': 'ou glisser pour déplacer' } },
            inlineToolbar: { converter: { 'Convert to': 'Convertir en' } },
            toolbar: { toolbox: { Add: 'Ajouter' } },
            popover: { Filter: 'Filtrer', 'Nothing found': 'Aucun résultat', 'Convert to': 'Convertir en' },
        },
        toolNames: {
            Text: 'Paragraphe', Heading: 'Titre', 'Unordered List': 'Liste à puces', 'Ordered List': 'Liste numérotée',
            Checklist: 'Liste de tâches', Quote: 'Citation', Code: 'Code', Delimiter: 'Séparateur', Table: 'Tableau',
            Warning: 'Avertissement', Image: 'Image', Link: 'Lien', Bold: 'Gras', Italic: 'Italique',
            InlineCode: 'Code en ligne', Marker: 'Surligner', Underline: 'Souligner', Color: 'Couleur du texte',
            Highlight: 'Surlignage coloré',
        },
        tools: {
            header: { 'Heading 2': 'Titre 2', 'Heading 3': 'Titre 3', 'Heading 4': 'Titre 4' },
            image: {
                Caption: 'Légende', 'Select an Image': 'Choisir une image', 'With border': 'Avec bordure',
                'Stretch image': 'Pleine largeur', 'With background': 'Avec fond',
            },
            link: { 'Add a link': 'Ajouter un lien' },
            list: { Ordered: 'Numérotée', Unordered: 'À puces', Checklist: 'Tâches' },
            quote: { 'Align Left': 'Aligner à gauche', 'Align Center': 'Centrer' },
            table: {
                'With headings': 'Avec en-têtes', 'Without headings': 'Sans en-têtes',
                'Add row above': 'Ligne au-dessus', 'Add row below': 'Ligne en dessous', 'Delete row': 'Supprimer la ligne',
                'Add column to left': 'Colonne à gauche', 'Add column to right': 'Colonne à droite', 'Delete column': 'Supprimer la colonne',
            },
            warning: { Title: 'Titre', Message: 'Message' },
            embed: { 'Enter a caption': 'Légende' },
            stub: { 'The block can not be displayed correctly.': 'Ce bloc ne peut pas être affiché.' },
        },
        blockTunes: {
            delete: { Delete: 'Supprimer', 'Click to delete': 'Confirmer la suppression' },
            moveUp: { 'Move up': 'Monter' },
            moveDown: { 'Move down': 'Descendre' },
        },
    },
};

function buildTools(uploadUrl, uploadByUrl) {
    return {
        alignment: { class: AlignmentTune, config: { default: 'left' } },
        paragraph: { inlineToolbar: true, tunes: ['alignment'] },
        header: {
            class: Header,
            inlineToolbar: true,
            tunes: ['alignment'],
            config: { levels: [2, 3, 4], defaultLevel: 2, placeholder: 'Titre' },
        },
        list: { class: ListWithNestedPaste, inlineToolbar: true, config: { defaultStyle: 'unordered' } },
        quote: { class: Quote, inlineToolbar: true, tunes: ['alignment'] },
        image: {
            class: ImageTool,
            config: {
                // byUrl : images collées (<img>, image en base64, lien direct) récupérées par le serveur
                endpoints: { byFile: uploadUrl, byUrl: uploadByUrl },
                field: 'image',
                types: 'image/png, image/jpeg, image/gif, image/webp',
                additionalRequestHeaders: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                captionPlaceholder: 'Légende',
                buttonContent: 'Choisir une image',
            },
        },
        code: { class: Code, config: { placeholder: 'Votre code…' } },
        table: { class: Table, inlineToolbar: true, config: { rows: 2, cols: 3, withHeadings: true } },
        warning: { class: Warning, inlineToolbar: true, config: { titlePlaceholder: 'Titre', messagePlaceholder: 'Message' } },
        delimiter: Delimiter,
        embed: { class: EmbedWithIframes, config: { services: { youtube: true, vimeo: true, codepen: true } } },
        inlineCode: { class: InlineCode },
        marker: { class: Marker },
        underline: Underline,
        Color: {
            class: ColorPlugin,
            config: { colorCollections: PALETTE, defaultColor: '#dc2626', type: 'text', customPicker: true },
        },
        Highlight: {
            class: ColorPlugin,
            config: { colorCollections: ['#fef08a', '#bbf7d0', '#bfdbfe', '#fbcfe8', '#fed7aa', '#e9d5ff'], defaultColor: '#fef08a', type: 'marker' },
        },
    };
}

/**
 * Monte un éditeur sur chaque élément [data-editorjs].
 *   data-input       : id du champ caché qui reçoit le JSON
 *   data-upload-url  : point de téléversement des images
 *   data-placeholder : texte d'invite
 */
export function mountEditors() {
    const forms = new Map();

    document.querySelectorAll('[data-editorjs]').forEach((holder) => {
        const input = document.getElementById(holder.dataset.input);
        let data;
        try {
            data = input?.value ? JSON.parse(input.value) : undefined;
        } catch {
            data = undefined;
        }

        const editor = new EditorJS({
            holder,
            data,
            i18n,
            minHeight: 120,
            placeholder: holder.dataset.placeholder || 'Commencez à écrire…',
            tools: buildTools(holder.dataset.uploadUrl, holder.dataset.uploadByUrl),
        });

        // Collage : pré-traitement du HTML (iframes) puis relance de l'événement pour Editor.js.
        holder.addEventListener('paste', (event) => {
            if (event.portfolioPrepared || !event.clipboardData) return;
            const html = preparePastedHtml(event.clipboardData.getData('text/html'));
            if (html === null) return;

            event.preventDefault();
            event.stopImmediatePropagation();
            const data = new DataTransfer();
            data.setData('text/html', html);
            data.setData('text/plain', event.clipboardData.getData('text/plain'));
            const prepared = new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true });
            prepared.portfolioPrepared = true;
            event.target.dispatchEvent(prepared);
        }, true);

        // Insertion d'une image déjà envoyée (galerie) : événement « editor-insert-image »
        // émis dans le même formulaire, avec { url, caption }.
        holder.closest('form')?.addEventListener('editor-insert-image', async (event) => {
            await editor.isReady;
            const { url, caption = '' } = event.detail || {};
            if (!url) return;
            // Après le bloc en cours d'édition, sinon à la fin du contenu.
            const current = editor.blocks.getCurrentBlockIndex();
            const index = current >= 0 ? current + 1 : editor.blocks.getBlocksCount();
            editor.blocks.insert('image', { file: { url }, caption, withBorder: false, withBackground: false, stretched: false },
                undefined, index, true);
            holder.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });

        const form = holder.closest('form');
        if (form && input) {
            if (!forms.has(form)) forms.set(form, []);
            forms.get(form).push({ editor, input });
        }
    });

    // À chaque envoi du formulaire, les éditeurs sont sérialisés dans leur champ caché,
    // puis l'envoi est relancé. Le contenu est toujours ré-enregistré : un envoi bloqué
    // (validation, double clic…) ne peut plus transmettre une version périmée.
    forms.forEach((editors, form) => {
        let resubmitting = false;
        form.addEventListener('submit', async (event) => {
            if (resubmitting) return;
            event.preventDefault();
            const submitter = event.submitter ?? undefined;

            for (const { editor, input } of editors) {
                input.value = JSON.stringify(await editor.save());
            }

            // Relance différée : appelé directement après la sauvegarde, requestSubmit()
            // est ignoré par Chrome (le formulaire ne partait pas).
            setTimeout(() => {
                resubmitting = true;
                try {
                    form.requestSubmit ? form.requestSubmit(submitter) : form.submit();
                } finally {
                    resubmitting = false;
                }
            }, 0);
        });
    });
}
